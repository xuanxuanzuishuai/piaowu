<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/26
 * Time: 10:29
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Util;
use Slim\Http\StatusCode;



class Common extends ControllerBase
{

    /** js_sdk config
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getJsConfig(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'url',
                'type' => 'required',
                'error_code' => 'url_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        $t = time();
        $noncestr = Util::token();
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $app_id = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $user_type = WeChatService::USER_TYPE_STUDENT;
        $signature = WeChatService::getJSSignature ($app_id, $user_type, $noncestr, $t, $params["url"]);
        $app_info = WeChatService::getWeCHatAppIdSecret($app_id, $user_type);
        $wxJSConfig = [
            'appId'     => $app_info["app_id"],
            'timestamp' => $t,
            'nonceStr'  => $noncestr,
            'signature' => $signature
        ];
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $wxJSConfig
        ], StatusCode::HTTP_OK);
    }
}
