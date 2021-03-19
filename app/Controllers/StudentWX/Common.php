<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/26
 * Time: 10:29
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserWeiXinModel;
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
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $t = time();
        $noncestr = Util::token();
        $appId= Constants::SMART_APP_ID;
        $wechat = WeChatMiniPro::factory($appId, DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER);
        $signature = $wechat->getJSSignature($noncestr, $t, $params["url"]);
        $wxJSConfig = [
            'appId'     => $appId,
            'timestamp' => $t,
            'nonceStr'  => $noncestr,
            'signature' => $signature
        ];
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $wxJSConfig,
        ], StatusCode::HTTP_OK);
    }
}
