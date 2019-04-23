<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ScheduleTaskModel;
use App\Services\ScheduleService;
use App\Services\ScheduleTaskService;
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
        if(isset($params['page'])) {
            list($params['page'], $params['count']) = Util::formatPageCount($params);
        }else {
            $params['page'] = -1 ;
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
                'key' => 'scheduleId',
                'type' => 'required',
                'error_code' => 'scheduleId_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }
        $schedule = ScheduleService::getDetail($params['scheduleId']);
        return $response->withJson([
            'code' => 0,
            'data' => ['schedule' =>$schedule]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     */
    public function modify(Request $request, Response $response, $args)
    {

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function append(Request $request, Response $response, $args) {
        $rules = [
            [
                'key' => 'st_id',
                'type' => 'required',
                'error_code' => 'st_id_is_required',
            ]
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

        $beginDate = empty($params['beginDate'])?date("Y-m-d"):$params['beginDate'];
        $endDate = date('Y-m-d',strtotime($beginDate,"+".$params['period']." w")+86400);

        $weekday = date("w");
        if($weekday <= $st['weekday']) {
            $beginTime = strtotime($beginDate." ".$st['start_time'])+86400*($st['weekday']-$weekday);
        }
        else {
            $beginTime = strtotime($beginDate." ".$st['start_time'])+86400*(7-($weekday-$st['weekday']));
        }

        if(empty($params['studentIds'])) {

        }
        if(empty($params['teacherIds'])) {
            return $response->withJson(Valid::addErrors([], 'schedule_task', 'schedule_task_teachers_is_empty'), StatusCode::HTTP_OK);
        }
    }
}