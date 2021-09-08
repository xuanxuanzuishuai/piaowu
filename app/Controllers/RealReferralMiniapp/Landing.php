<?php


namespace App\Controllers\RealReferralMiniapp;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\Valid;
use App\Services\CommonServiceForApp;
use App\Services\RealReferralService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Landing extends ControllerBase
{
    public function register(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules  = [
            [
                'key'        => 'iv',
                'type'       => 'required',
                'error_code' => 'iv_is_required'
            ],
            [
                'key'        => 'encrypted_data',
                'type'       => 'required',
                'error_code' => 'encrypted_data_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['openid'] = $this->ci['real_referral_miniapp_openid'];
            $data = RealReferralService::register($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 小程序首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['open_id'] = $this->ci['real_referral_miniapp_openid'];
            $pageData = RealReferralService::index($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 获取学生状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentStatus(Request $request, Response $response)
    {
        try {
            $studentId = $this->ci['real_referral_miniapp_userid'] ?? '';
            $pageData = RealReferralService::getStudentStatus($studentId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 验证码登录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules  = [
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key'        => 'sms_code',
                'type'       => 'required',
                'error_code' => 'sms_code_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $countryCode = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
        if (!CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"], $countryCode)) {
            return $response->withJson(Valid::addAppErrors([], 'incorrect_mobile_phone_number_or_verification_code'), StatusCode::HTTP_OK);
        }
        try {
            $params['openid'] = $this->ci['real_referral_miniapp_openid'];
            $data = RealReferralService::register($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }



}