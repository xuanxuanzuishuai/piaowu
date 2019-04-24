<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\ScheduleTaskModel;
use App\Models\ScheduleTaskUserModel;
use App\Services\ClassroomService;
use App\Services\CourseService;
use App\Services\ScheduleService;
use App\Services\ScheduleTaskService;
use App\Services\ScheduleTaskUserService;
use App\Services\ScheduleUserService;
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
            [
                'key' => 'weekday',
                'type' => 'in',
                'value' => [0, 1, 2, 3, 4, 5, 6],
                'error_code' => 'weekday_is_invalid',
            ],
            [
                'key' => 'expire_start_date',
                'type' => 'required',
                'error_code' => 'expire_start_date_is_required',
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
            'expire_start_date' => $params['expire_start_date'],
            'create_time' => time(),
            'status' => ScheduleTaskModel::STATUS_NORMAL,
        ];
        if (!empty($params['expire_end_date'])) {
            if ($params['expire_end_date'] <= $params['expire_start_date']) {
                return $response->withJson(Valid::addErrors([], 'schedule_task_expire', 'expire_end_date_before_start_date'), StatusCode::HTTP_OK);
            }
            $st['expire_end_date'] = $params['expire_end_date'];
        }
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
            $result = ScheduleTaskService::checkStudent($params['studentIds'], $params['start_time'], $params['end_time'], $params['weekday'], $params['expire_start_date']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_student', 'schedule_task_student_time_error'), StatusCode::HTTP_OK);
            }
        }
        if (!empty($params['teacherIds'])) {
            $result = ScheduleTaskService::checkTeacher($params['teacherIds'], $params['start_time'], $params['end_time'], $params['weekday'], $params['expire_start_date']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_teacher', 'schedule_task_teacher_time_error'), StatusCode::HTTP_OK);
            }
        }
        $stId = ScheduleTaskService::addST($st, $params['studentIds'], $params['teacherIds']);
        if (empty($stId)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_failure', 'schedule_task_add_failure'), StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($stId);
        return $response->withJson([
            'code' => 0,
            'data' => ['st' => $st]
        ], 200);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'st_id_is_required',
            ],
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
            [
                'key' => 'weekday',
                'type' => 'in',
                'value' => [0, 1, 2, 3, 4, 5, 6],
                'error_code' => 'weekday_is_invalid',
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required',
            ],
            [
                'key' => 'expire_start_date',
                'type' => 'required',
                'error_code' => 'expire_start_date_is_required',
            ],
            [
                'key' => 'expire_end_date',
                'type' => 'required',
                'error_code' => 'expire_end_date_is_required',
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
        if ($params['expire_end_date'] > '0000-00-00' && $params['expire_end_date'] <= $params['expire_start_date']) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_expire', 'expire_end_date_before_start_date'), StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if (empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if ($st['status'] != ScheduleTaskModel::STATUS_NORMAL) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_1exist'), StatusCode::HTTP_OK);
        }
        $newSt['id'] = $st['id'];
        $newSt['course_id'] = $params['course_id'];
        $newSt['classroom_id'] = $params['classroom_id'];
        $newSt['status'] = $params['status'];
        $newSt['start_time'] = $params['start_time'];
        $newSt['end_time'] = $params['end_time'];
        $newSt['weekday'] = $params['weekday'];
        $newSt['expire_start_date'] = $params['expire_start_date'];
        $newSt['expire_end_date'] = $params['expire_end_date'];
        $newSt['update_time'] = time();
        if (!empty($params['classroom_id'])) {
            $classroom = ClassroomService::getById($params['classroom_id']);
            if (empty($classroom)) {
                return $response->withJson(Valid::addErrors([], 'schedule_classroom', 'schedule_classroom_not_exist'), StatusCode::HTTP_OK);
            }
        }

        if (!empty($params['course_id'])) {
            $course = CourseService::getCourseById($params['course_id']);
            if (empty($course)) {
                return $response->withJson(Valid::addErrors([], 'schedule_course', 'schedule_course_not_exist'), StatusCode::HTTP_OK);
            }
        }
        $result = ScheduleTaskService::checkST($newSt);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_classroom', 'schedule_task_classroom_conflict'), StatusCode::HTTP_OK);
        }

        $studentIds = [];
        if (!empty($st['students'])) {
            foreach ($st['students'] as $student) {
                $studentIds[] = $student['user_id'];
                $ssuIds[] = $student['id'];
            }
        }
        if (!empty($params['studentIds'])) {
            if (count($params['studentIds']) > $st['class_highest']) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_student', 'schedule_task_student_num_more_than_max'), StatusCode::HTTP_OK);
            }
            $result = ScheduleTaskService::checkStudent($params['studentIds'], $newSt['start_time'], $newSt['end_time'], $newSt['weekday'], $newSt['expire_start_date'], $newSt['id']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_student', 'schedule_task_student_time_conflict'), StatusCode::HTTP_OK);
            }
        }
        $teacherIds = [];
        if (!empty($st['teachers'])) {
            foreach ($st['teachers'] as $teacher) {
                $teacherIds[] = $teacher['user_id'];
                $stuIds[] = $teacher['id'];
            }
        }
        if (!empty($params['teacherIds'])) {
            $result = ScheduleTaskService::checkTeacher($params['teacherIds'], $newSt['start_time'], $newSt['end_time'], $newSt['weekday'], $newSt['expire_start_date'], $newSt['id']);
            if ($result !== true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_task_teacher', 'schedule_task_teacher_time_conflict'), StatusCode::HTTP_OK);
            }
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        ScheduleTaskService::modifyST($newSt);
        if ($newSt['status'] == ScheduleTaskModel::STATUS_CANCEL) {
            ScheduleTaskUserService::unBindUser(array_merge($ssuIds, $stuIds), $st['id']);
        } else {
            if ($studentIds != $params['studentIds']) {
                ScheduleTaskUserService::unBindUser($ssuIds, $st['id']);
                ScheduleTaskUserService::bindSTUs([$newSt['id']], [ScheduleTaskUserModel::USER_ROLE_S => $params['studentIds']]);
            }
            if ($teacherIds != $params['teacherIds']) {
                ScheduleTaskUserService::unBindUser($stuIds, $st['id']);
                ScheduleTaskUserService::bindSTUs([$newSt['id']], [ScheduleTaskUserModel::USER_ROLE_T => $params['teacherIds']]);
            }
        }
        $db->commit();
        $st = ScheduleTaskService::getSTDetail($newSt['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st' => $st]
        ]);

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
        if (empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if ($st['status'] != ScheduleTaskModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_status_invalid'), StatusCode::HTTP_OK);
        }
        if (count($params['studentIds']) + count($st['students']) > $st['class_highest']) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_num_more_than_max'), StatusCode::HTTP_OK);
        }
        $result = ScheduleTaskService::checkStudent($params['studentIds'], $st['start_time'], $st['end_time'], $st['weekday'], $st['expire_start_date']);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_time_error'), StatusCode::HTTP_OK);
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = ScheduleService::bindSUs($st['id'], $params['studentIds'], ScheduleTaskUserModel::USER_ROLE_S);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_student_error'), StatusCode::HTTP_OK);
        }
        $res = ScheduleTaskUserService::bindSTUs([$st['id']], [ScheduleTaskUserModel::USER_ROLE_S => $params['studentIds']]);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_student_error'), StatusCode::HTTP_OK);
        }
        $db->commit();
        $st = ScheduleTaskService::getSTDetail($st['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st' => $st],
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
        if (empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if ($st['status'] != ScheduleTaskModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_status_invalid'), StatusCode::HTTP_OK);
        }
        if (!empty($st['teachers'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_exist'), StatusCode::HTTP_OK);
        }
        $result = ScheduleTaskService::checkTeacher($params['teacherIds'], $st['start_time'], $st['end_time'], $st['weekday'], $st['expire_start_date']);
        if ($result !== true) {
            return $response->withJson(Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_time_error'), StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = ScheduleService::bindSUs($st['id'], $params['teacherIds'], ScheduleTaskUserModel::USER_ROLE_T);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_teacher_error'), StatusCode::HTTP_OK);
        }
        $res = ScheduleTaskUserService::bindSTUs([$st['id']], [ScheduleTaskUserModel::USER_ROLE_T => $params['teacherIds']]);
        if ($res == false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'schedule_task_bind_student', 'schedule_task_bind_teacher_error'), StatusCode::HTTP_OK);
        }
        $db->commit();
        $st = ScheduleTaskService::getSTDetail($st['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st' => $st]
        ], StatusCode::HTTP_OK);

    }

    /**
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
                'error_code' => 'user_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if (empty($st) || $st['status'] != ScheduleTaskModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        $users = $stuIds = [];
        if (!empty($st['students'])) {
            foreach ($st['students'] as $user) {
                if (in_array($user['id'], $params['stu_ids'])) {
                    $users[ScheduleTaskUserModel::USER_ROLE_S][] = $user['user_id'];
                    $stuIds[] = $user['id'];
                }
            }
        }
        if (!empty($st['teachers'])) {
            foreach ($st['teachers'] as $user) {
                if (in_array($user['id'], $params['stu_ids'])) {
                    $users[ScheduleTaskUserModel::USER_ROLE_T][] = $user['user_id'];
                    $stuIds[] = $user['id'];
                }
            }
        }
        $beginDate = empty($params['beginDate']) ? date("Y-m-d") : $params['beginDate'];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        if(!empty($users)) {
            ScheduleUserService::cancelScheduleUsers($users, $st['id'], $beginDate);
        }
        if(!empty($stuIds)) {
            ScheduleTaskUserService::unBindUser($stuIds, $st['id']);
        }
        $db->commit();

        $st = ScheduleTaskService::getSTDetail($st['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['st' => $st]
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
        if (isset($params['page'])) {
            list($params['page'], $params['count']) = Util::formatPageCount($params);
        } else {
            $params['page'] = -1;
        }
        $sts = ScheduleTaskService::getSTList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => ['count' => $sts[0], 'sts' => $sts[1]]
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

    /**
     * 开课
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function beginST(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'schedule_task_id_is_required'
            ],
            [
                'key' => 'period',
                'type' => 'required',
                'error_code' => 'period_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if (empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if ($st['status'] != ScheduleTaskModel::STATUS_NORMAL) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_status_invalid'), StatusCode::HTTP_OK);
        }
        if (empty($st['students'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_students_is_empty'), StatusCode::HTTP_OK);
        }
        if (empty($st['teachers'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_teachers_is_empty'), StatusCode::HTTP_OK);
        }
        $beginDate = $st['expire_start_date'];
        $endDate = date('Y-m-d', strtotime($beginDate, "+" . $params['period'] . " w") + 86400);
        $weekday = date("w");
        if ($weekday <= $st['weekday']) {
            $beginTime = strtotime($beginDate . " " . $st['start_time']) + 86400 * ($st['weekday'] - $weekday);
        } else {
            $beginTime = strtotime($beginDate . " " . $st['start_time']) + 86400 * (7 - ($weekday - $st['weekday']));
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $result = ScheduleService::beginSchedule($st, $params, $beginTime);
        if ($result === false) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_task_create_failure'), StatusCode::HTTP_OK);
        } else {
            ScheduleTaskService::modifyST(['id' => $st['id'], 'status' => ScheduleTaskModel::STATUS_BEGIN, 'expire_end_date' => $endDate]);
        }
        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function cancelST(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'schedule_task_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $st = ScheduleTaskService::getSTDetail($params['st_id']);
        if (empty($st)) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_not_exist'), StatusCode::HTTP_OK);
        }
        if ($st['status'] != ScheduleTaskModel::STATUS_BEGIN) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_status_invalid'), StatusCode::HTTP_OK);
        }
        if (!empty($st['students'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_students_is_not_empty'), StatusCode::HTTP_OK);
        }
        if (!empty($st['teachers'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_teachers_is_not_empty'), StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = ScheduleService::cancelScheduleBySTId($st['id']);
        if ($res == false) {
            $db->rollBack();
        }
        $res = ScheduleTaskService::modifyST(['id' => $st['id'], 'status' => ScheduleTaskModel::STATUS_CANCEL, 'update_time' => time()]);
        if ($res == false) {
            $db->rollBack();
        }
        $db->commit();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
            ]
        ], StatusCode::HTTP_OK);

    }
}