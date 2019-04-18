<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 17:57
 */

namespace App\Controllers\Schedule;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\ScheduleTaskService;
use Slim\Http\Response;
use Slim\Http\Request;
use Slim\Http\StatusCode;

class ScheduleTask extends  ControllerBase
{
    public function add(Request $request,Response $response,$args) {

    }

    public function modify(Request $request,Response $response,$args) {

    }

    public function list(Request $request,Response $response,$args) {
        $params = $request->getParams();
        $sts = ScheduleTaskService::getSTList();

    }

    public function detail(Request $request,Response $response,$args) {
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