<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/5/11
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\Valid;
use App\Services\CommonServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Admin extends ControllerBase
{
    /**
     * 创建验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function smsCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'code_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $mobile = $params['mobile'];
        $code = $params['code'];
        $countryCode = $params['country'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
        $cacheKey = CommonServiceForApp::VALIDATE_CODE_CACHE_KEY_PRI . $countryCode . $mobile;
        $redis = RedisDB::getConn();
        $redis->setex($cacheKey, CommonServiceForApp::INT_VALIDATE_CODE_EX, $code);
        return HttpHelper::buildResponse($response, []);
    }
}
