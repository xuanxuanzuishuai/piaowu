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
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;
use App\Models\STClassModel;
use App\Services\ClassroomService;
use App\Services\ClassTaskService;
use App\Services\ClassUserService;
use App\Services\CourseService;
use App\Services\ScheduleService;
use App\Services\ScheduleUserService;
use App\Services\STClassService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ClassTask extends ControllerBase
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
        if ($st['status'] != ClassTaskModel::STATUS_NORMAL) {
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
            ScheduleTaskService::modifyST(['id' => $st['id'], 'status' => ClassTaskModel::STATUS_BEGIN, 'expire_end_date' => $endDate]);
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
        if ($st['status'] != ClassTaskModel::STATUS_BEGIN) {
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
        $res = ScheduleTaskService::modifyST(['id' => $st['id'], 'status' => ClassTaskModel::STATUS_CANCEL, 'update_time' => time()]);
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