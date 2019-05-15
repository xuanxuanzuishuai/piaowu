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
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ScheduleModel;
use App\Services\ClassroomService;
use App\Services\CourseService;
use App\Services\ScheduleService;
use App\Services\ScheduleUserService;
use App\Services\STClassService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Schedule extends ControllerBase
{
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
        $schedules = ScheduleService::getList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => ['count' => $schedules[0], 'sts' => $schedules[1]]
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
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);

    }

    /**
     * 调课
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ],
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required',
            ],
            [
                'key' => 'classroom_id',
                'type' => 'required',
                'error_code' => 'classroom_id_is_required',
            ],
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'course_class_highest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'integer',
                'error_code' => 'course_class_highest_must_be_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'), StatusCode::HTTP_OK);
        }

        $classroom = ClassroomService::getById($params['classroom_id']);
        $course = CourseService::getCourseById($params['course_id']);
        $params['start_time'] = strtotime($params['start_time']);
        $now = time();

        $newSchedule['id'] = $schedule['id'];
        $newSchedule['start_time'] = $params['start_time'];
        $newSchedule['end_time'] = $params['start_time'] + $course['duration'];
        $newSchedule['classroom_id'] = $params['classroom_id'];
        $newSchedule['course_id'] = $params['course_id'];
        $newSchedule['duration'] = $course['duration'];
        $newSchedule['update_time'] = $now;
        $newSchedule['org_id'] = $classroom['org_id'];
        $newSchedule['class_id'] = $schedule['class_id'];

        $classTasks = ScheduleService::checkScheduleAndClassTask($params, $newSchedule, $now);
        if (!empty($classTasks) && $classTasks['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($classTasks, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $result = ScheduleService::modifySchedule($newSchedule, $classTasks, $params, $now);
        if (!empty($result)) {
            $db->rollBack();
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $db->commit();

        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 学生请假
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function takeOff(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ],
            [
                'key' => 'su_ids',
                'type' => 'required',
                'error_code' => 'user_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'), StatusCode::HTTP_OK);
        }
        if (!is_array($params['su_ids'])) {
            return $response->withJson(Valid::addErrors([], 'su_ids', 'su_id_is_required'), StatusCode::HTTP_OK);
        }

        ScheduleUserService::takeOff($params['schedule_id'], $params['su_ids']);

        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 签到
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function signIn(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ],
            [
                'key' => 'su_ids',
                'type' => 'required',
                'error_code' => 'su_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'), StatusCode::HTTP_OK);
        }
        if (!is_array($params['su_ids'])) {
            return $response->withJson(Valid::addErrors([], 'su_ids', 'su_id_is_required'), StatusCode::HTTP_OK);
        }

        $employeeId = $this->getEmployeeId();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = ScheduleUserService::signIn($params['schedule_id'], $params['su_ids'], $schedule['students'], $employeeId);
        if ($res !== true) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }
        $db->commit();
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 下课
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function finish(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'), StatusCode::HTTP_OK);
        }
        if ($schedule['end_time'] >= time()) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_end'), StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        ScheduleService::finish($schedule);
        $db->commit();
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 添加课程
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required',
            ],
            [
                'key' => 'classroom_id',
                'type' => 'required',
                'error_code' => 'classroom_id_is_required',
            ],
            [
                'key' => 'course_id',
                'type' => 'required',
                'error_code' => 'course_id_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'required',
                'error_code' => 'course_class_highest_is_required',
            ],
            [
                'key' => 'class_highest',
                'type' => 'integer',
                'error_code' => 'course_class_highest_must_be_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $classroom = ClassroomService::getById($params['classroom_id']);
        $course = CourseService::getCourseById($params['course_id']);
        $params['start_time'] = strtotime($params['start_time']);
        $now = time();

        $schedule['start_time'] = $params['start_time'];
        $schedule['end_time'] = $params['start_time'] + $course['duration'];
        $schedule['status'] = ScheduleModel::STATUS_BOOK;
        $schedule['classroom_id'] = $params['classroom_id'];
        $schedule['course_id'] = $params['course_id'];
        $schedule['create_time'] = $now;
        $schedule['org_id'] = $classroom['org_id'];
        $schedule['duration'] = $course['duration'];

        $classTasks = ScheduleService::checkScheduleAndClassTask($params, $schedule, $now);
        if (!empty($classTasks['code'])) {
            return $response->withJson($classTasks, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $scheduleId = ScheduleService::addSchedule($classTasks, $schedule, $now, $params);

        if (!empty($scheduleId['code'])) {
            $db->rollBack();
            return $response->withJson($scheduleId, StatusCode::HTTP_OK);
        }
        $db->commit();

        $schedule = ScheduleService::getDetail($scheduleId);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 取消课次
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function cancel(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'schedule_id',
                'type' => 'required',
                'error_code' => 'schedule_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'), StatusCode::HTTP_OK);
        }

        ScheduleService::cancelSchedule($schedule);
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);
    }

}