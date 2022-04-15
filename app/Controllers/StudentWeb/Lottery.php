<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2022/4/12
 * Time: 3:11 下午
 */

namespace App\Controllers\StudentWeb;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\Activity\Lottery\LotteryClientService;
use App\Services\Activity\Lottery\LotteryServices\LotteryActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Lottery extends ControllerBase
{
    public function activityInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'op_activity_id',
                'type' => 'required',
                'error_code' => 'op_activity_id_is_required',
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $activeInfo = LotteryClientService::activityInfo($params);
        return HttpHelper::buildResponse($response,$activeInfo);
    }

    public function startLottery(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'op_activity_id',
                'type' => 'required',
                'error_code' => 'op_activity_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['uuid'] = $this->ci['user_info']['uuid'];

        $hitAwardInfo = LotteryClientService::hitAwardInfo($params);
        return HttpHelper::buildResponse($response,$hitAwardInfo);
    }
}