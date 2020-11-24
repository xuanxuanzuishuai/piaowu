<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/27
 * Time: 2:05 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Consumer extends ControllerBase
{
    /**
     * 更新不同系统的access_token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAccessToken(Request $request, Response $response)
    {
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
        WeChatMiniPro::factory($params['msg_body']['source_app_id'], $params['msg_body']['busi_type'])->setAccessToken($params['msg_body']['access_token']);
    }
}