<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 15:41
 */

namespace App\Controllers\Real;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\CommonServiceForApp;
use App\Services\RealStudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 真人业务线学生端授权接口控制器文件
 * Class StudentActivity
 * @package App\Routers
 */
class StudentAuth extends ControllerBase
{
    /**
     * 学生手机号和验证码注册
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function smsCodeRegister(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'sms_code',
                'type' => 'required',
                'error_code' => 'validate_code_error'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['country_code'] = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
        $params['channel_id'] = $params['channel_id'] ?? 0;
        $params['login_type'] = $params['login_type'] ?? Constants::REAL_STUDENT_LOGIN_TYPE_MAIN_LESSON_H5;
        // 验证手机验证码
        if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['sms_code'], $params['country_code'])) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        try {
            $result = RealStudentService::register($params['mobile'], $params['country_code'], $params['channel_id'], [], [], $params['login_type']);
        } Catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 发送注册验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendSmsCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        empty($params['country_code']) && $params['country_code'] = NewSMS::DEFAULT_COUNTRY_CODE;
        // 校验手机号
        $phoneNumberValid = Util::validPhoneNumber($params['mobile'], $params['country_code']);
        if (empty($phoneNumberValid)) {
            return $response->withJson(Valid::addAppErrors([], 'invalid_mobile'), StatusCode::HTTP_OK);
        }
        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'], CommonServiceForApp::SIGN_WX_STUDENT_APP, $params['country_code']);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }
}
