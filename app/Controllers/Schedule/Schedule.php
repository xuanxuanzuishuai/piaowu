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
use App\Models\ScheduleTaskModel;
use App\Models\ScheduleTaskUserModel;
use App\Models\ScheduleUserModel;
use App\Services\ScheduleService;
use App\Services\ScheduleTaskService;
use App\Services\ScheduleUserService;
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
            return $response->withJson($result, 200);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ], StatusCode::HTTP_OK);

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
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required',
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
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'));
        }

        $newSchedule['id'] = $schedule['id'];
        $newSchedule['start_time'] = strtotime($params['start_time']);
        $newSchedule['end_time'] = strtotime($params['end_time']);
        $newSchedule['status'] = $params['status'];
        $newSchedule['classroom_id'] = $params['classroom_id'];
        $newSchedule['course_id'] = $params['course_id'];
        $newSchedule['update_time'] = time();

        $result = ScheduleService::checkSchedule($newSchedule);
        if ($result != true) {
            return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_classroom', 'schedule_classroom_'));
        }
        $studentIds = $ssuIds = $stuIds = [];
        if (!empty($schedule['students'])) {
            foreach ($schedule['students'] as $student) {
                $studentIds[] = $student['user_id'];
                $ssuIds[] = $student['id'];
            }
        }
        if (!empty($params['studentIds'])) {
            $result = ScheduleUserService::checkScheduleUser($params['studentIds'], ScheduleTaskUserModel::USER_ROLE_S, $newSchedule['start_time'], $newSchedule['end_time'], $newSchedule['id']);
            if ($result != true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_classroom', 'schedule_classroom_'));
            }
        }
        $teacherIds = [];
        if (!empty($schedule['teachers'])) {
            foreach ($schedule['teachers'] as $teacher) {
                $teacherIds[] = $teacher['user_id'];
                $stuIds[] = $teacher['id'];
            }
        }
        if (!empty($params['teacherIds'])) {
            $result = ScheduleUserService::checkScheduleUser($params['teacherIds'], ScheduleTaskUserModel::USER_ROLE_T, $newSchedule['start_time'], $newSchedule['end_time'], $newSchedule['id']);
            if ($result != true) {
                return $response->withJson(Valid::addErrors(['data' => ['result' => $result]], 'schedule_classroom', 'schedule_classroom_'));
            }
        }


        $db = MysqlDB::getDB();
        $db->beginTransaction();
        ScheduleService::modifySchedule($newSchedule);
        if ($newSchedule['status'] == ScheduleModel::STATUS_CANCEL) {
            ScheduleUserService::unBindUser(array_merge($ssuIds, $stuIds));
        } else {
            if ($params['studentIds'] != $studentIds) {
                if (!empty($ssuIds)) {
                    ScheduleUserService::unBindUser($ssuIds);
                }
                ScheduleUserService::bindSUs([$newSchedule['id']], [ScheduleTaskUserModel::USER_ROLE_S => $params['studentIds']]);
            }
            if ($params['teacherIds'] != $teacherIds) {
                if (!empty($stuIds)) {
                    ScheduleUserService::unBindUser($stuIds);
                }
                ScheduleUserService::bindSUs([$newSchedule['id']], [ScheduleTaskUserModel::USER_ROLE_T => $params['teacherIds']]);
            }
        }
        $st = [
            'classroom_id' => $params['classroom_id'],
            'start_time' => date('H:i', strtotime($params['start_time'])),
            'end_time' => date('H:i', strtotime($params['end_time'])),
            'course_id' => $params['course_id'],
            'weekday' => date('w', strtotime($params['start_time'])),
            'expire_start_date' => date("Y-m-d", strtotime($params['start_time'])),
            'expire_end_date' => date("Y-m-d", strtotime($params['start_time']) + 86400),
            'create_time' => time(),
            'real_schedule_id' => $schedule['id'],
            'status' => ScheduleTaskModel::STATUS_TEMP,
        ];
        ScheduleTaskService::addST($st, $params['studentIds'], $params['teacherIds']);
        $db->commit();

        $schedule = ScheduleService::getDetail($newSchedule['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ]);
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
            ],
            [
                'key' => 'user_role',
                'type' => 'required',
                'error_code' => 'user_role_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'));
        }

        $ssuIds = $stuIds = [];
        //学员请假
        if ($params['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
            foreach ($schedule['students'] as $su) {
                if (in_array($su['id'], $params['su_ids']) && $su['user_status'] == ScheduleUserModel::STUDENT_STATUS_BOOK) {
                    $ssuIds[] = $su['id'];
                }
            }
            if (!empty($ssuIds)) {
                ScheduleUserService::takeOff($ssuIds, ScheduleTaskUserModel::USER_ROLE_S);
            }
        } //老师请假
        else if ($params['user_role'] == ScheduleTaskUserModel::USER_ROLE_T) {
            foreach ($schedule['teachers'] as $su) {
                if (in_array($su['id'], $params['su_ids']) && $su['user_status'] == ScheduleUserModel::TEACHER_STATUS_SET) {
                    $stuIds[] = $su['id'];
                }
            }
            if (!empty($stuIds)) {
                ScheduleUserService::takeOff($stuIds, ScheduleTaskUserModel::USER_ROLE_T);
            }
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ]);
    }

    /**
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
            ],
            [
                'key' => 'user_role',
                'type' => 'required',
                'error_code' => 'user_role_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        if (empty($schedule) || $schedule['status'] != ScheduleModel::STATUS_BOOK) {
            return $response->withJson(Valid::addErrors([], 'schedule', 'schedule_not_exist'));
        }

        $ssuIds = $stuIds = [];
        //学员签到
        if ($params['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
            foreach ($schedule['students'] as $su) {
                if (in_array($su['id'], $params['su_ids'])) {
                    $ssuIds[] = $su['id'];
                }
            }
            if (!empty($ssuIds)) {
                ScheduleUserService::signIn($ssuIds, ScheduleTaskUserModel::USER_ROLE_S);
            }
        } //老师签到
        else if ($params['user_role'] == ScheduleTaskUserModel::USER_ROLE_T) {
            foreach ($schedule['teachers'] as $su) {
                if (in_array($su['id'], $params['su_ids'])) {
                    $stuIds[] = $su['id'];
                }
            }
            if (!empty($stuIds)) {
                ScheduleUserService::signIn($stuIds, ScheduleTaskUserModel::USER_ROLE_T);
            }
        }
        $schedule = ScheduleService::getDetail($params['schedule_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' => $schedule]
        ]);
    }

}