<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\ScheduleTaskModel;
use App\Models\ScheduleTaskUserModel;
use App\Services\ClassroomService;
use App\Services\CourseService;
use App\Services\ScheduleTaskService;
use App\Services\ScheduleTaskUserService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ScheduleTask extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'classroom_id',
                'type' => 'required',
                'error_code' => 'classroom_id_is_required',
            ],
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required',
            ],
            [
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required',
            ],
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'weekday',
                'type' => 'required',
                'error_code' => 'weekday_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        if ($params['start_time'] >= $params['end_time']) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_time', 'end_time_before_start_time'), StatusCode::HTTP_OK);
        }
        $classroom = ClassroomService::getById($params['classroom_id']);
        if (empty($classroom)) {
            return $response->withJson(Valid::addErrors([], 'schedule_classroom', 'schedule_classroom_not_exist'), StatusCode::HTTP_OK);
        }

        $course = CourseService::getCourseById($params['course_id']);
        if (empty($course)) {
            return $response->withJson(Valid::addErrors([], 'schedule_course', 'schedule_course_not_exist'), StatusCode::HTTP_OK);
        }
        $st = [
            'classroom_id' => $params['classroom_id'],
            'start_time' => $params['start_time'],
            'end_time' => $params['end_time'],
            'course_id' => $params['course_id'],
            'weekday' => $params['weekday'],
            'create_time' => time(),
            'status' => ScheduleTaskModel::STATUS_NORMAL,
        ];
        global $orgId;
        if ($orgId > 0) {
            $st['org_id'] = $orgId;
        } elseif (!empty($params['org_id']) && $this->ci['employee']['role'] == EmployeeModel::SUPER_ADMIN_ROLE_ID) {
            $st['org_id'] = $params['org_id'];
        }
        if (empty($st['org_id'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_org_id', 'org_id_is_empty'), StatusCode::HTTP_OK);
        }

        $result = ScheduleTaskService::checkST($st);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_classroom', 'schedule_task_classroom_error'), StatusCode::HTTP_OK);
        }
        if (!empty($params['studentIds'])) {
            if (count($params['studentIds']) > $course['class_highest']) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_student', 'schedule_task_student_num_more_than_max'), StatusCode::HTTP_OK);
            }
            $result = ScheduleTaskService::checkStudent($params['studentIds'], $params['start_time'], $params['end_time'], $params['weekday']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_student', 'schedule_task_student_time_error'), StatusCode::HTTP_OK);
            }
        }
        if (!empty($params['teacherIds'])) {
            $result = ScheduleTaskService::checkTeacher($params['teacherIds'], $params['start_time'], $params['end_time'], $params['weekday']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_teacher', 'schedule_task_teacher_time_error'), StatusCode::HTTP_OK);
            }
        }
        $result = ScheduleTaskService::addST($st, $params['studentIds'], $params['teacherIds']);
        if (empty($result)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_failure', 'schedule_task_add_failure'), StatusCode::HTTP_OK);
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['st_id' => $result]
        ], 200);
    }

    public function modify(Request $request, Response $response, $args)
    {

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindStudents(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'st_is_required',
            ],
            [
                'key' => 'studentIds',
                'type' => 'required',
                'error_code' => 'studentIds_is_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if(empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if (count($params['studentIds']) + count($st['students']) > $st['class_highest']) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_num_more_than_max'), StatusCode::HTTP_OK);
        }
        $result = ScheduleTaskService::checkStudent($params['studentIds'], $st['start_time'], $st['end_time'], $st['weekday']);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_time_error'), StatusCode::HTTP_OK);
        }

        $res = ScheduleTaskUserService::bindSTUs($st['id'],[ScheduleTaskUserModel::USER_ROLE_S=>[$params['studentIds']]]);
        if($res == false) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_student_error'), StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($st['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st'=> $st],
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function bindTeachers(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'st_is_required',
            ],
            [
                'key' => 'teacherIds',
                'type' => 'required',
                'error_code' => 'teacherIds_is_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);

        if (!empty($st['teachers'])) {
            foreach($st['teachers']  as $stu) {
                if($stu['status'] == ScheduleTaskUserModel::STATUS_NORMAL) {
                    return $response->withJson(Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_exist'), StatusCode::HTTP_OK);
                }
            }
        }
        $result = ScheduleTaskService::checkTeacher($params['teacherIds'], $st['start_time'], $st['end_time'], $st['weekday']);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_time_error'), StatusCode::HTTP_OK);
        }

        $res = ScheduleTaskUserService::bindSTUs($st['id'],[ScheduleTaskUserModel::USER_ROLE_T=>[$params['teacherIds']]]);
        if($res == false) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_student_error'), StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($st['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st'=>$st]
        ], StatusCode::HTTP_OK);

    }

    /**
     * Todo
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function unbindUsers(Request $request, Response $response, $args)
    {

        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'st_is_required',
            ],
            [
                'key' => 'stu_ids',
                'type' => 'required',
                'error_code' => 'stu_ids_are_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if(empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if(empty($st['students'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_student_not_exist'), StatusCode::HTTP_OK);
        }else {
            ScheduleTaskUserService::unBindUser($params['stu_ids']);
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['st'=>0]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $sts = ScheduleTaskService::getSTList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => ['count'=>$sts[0],'sts'=>$sts[1]]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'schedule_task_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($params['id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'st' => $st
            ]
        ], StatusCode::HTTP_OK);
    }
}