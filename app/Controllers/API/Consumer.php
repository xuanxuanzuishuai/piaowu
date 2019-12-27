<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/27
 * Time: 2:05 PM
 */

namespace App\Controllers\API;

use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\ChannelService;
use App\Controllers\ControllerBase;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Consumer extends ControllerBase
{
    public function channelStatus(Request $request, Response $response)
    {
        // 参数校验
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = 0;
        $body = $params['msg_body'];
        if (isset($body['app_id'])) {
            if ($body['app_id'] == Constants::APP_ID) {
                unset($body['app_id']);
                $res = ChannelService::syncChannel($body);
            }
        }

        return HttpHelper::buildResponse($response, ['res' => $res]);
    }
}