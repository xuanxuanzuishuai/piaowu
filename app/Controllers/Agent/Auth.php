<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:50
 */

namespace App\Controllers\Agent;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\UserWeiXinModel;
use App\Services\CommonServiceForApp;
use App\Services\AgentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\AgentModel;

class Auth extends ControllerBase
{

    /**
     * 代理登录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        if (isset($params['encrypted_data'])) {
            $rules = [
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
        } else {
            $rules = [
                [
                    'key'        => 'mobile',
                    'type'       => 'required',
                    'error_code' => 'mobile_is_required'
                ],
                [
                    'key'        => 'sms_code',
                    'type'       => 'required',
                    'error_code' => 'validate_code_error'
                ]
            ];
        }
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        // 验证手机验证码
        if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['sms_code'], $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE)) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        try {
            $appId       = UserCenter::AUTH_APP_ID_OP_AGENT;
            $openId      = $this->ci['open_id'];
            $unionId     = $this->ci['unionid'] ?? '';
            $mobile      = $params['mobile'] ?? '';
            $countryCode = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;

            if (!empty($params['encrypted_data'])) {
                $wechat        = WeChatMiniPro::factory($appId, UserWeiXinModel::BUSI_TYPE_AGENT_MINI);
                $decryptedData = $wechat->decryptMobile($openId, $params['iv'], $params['encrypted_data']);
                if (empty($decryptedData)) {
                    SimpleLogger::error('EMPTY DECRYPTED DATA', [$openId, $params]);
                    throw new RunTimeException(['decrypt_error']);
                }
                $mobile = $decryptedData['purePhoneNumber'];
                $countryCode = $decryptedData['countryCode'];
            }

            list($token, $userInfo) = AgentService::bindAgentWechat($appId, $mobile, $openId, $unionId, $countryCode);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse(
            $response,
            ['token' => $token, 'user' => $userInfo]
        );
    }

    /**
     * 退出登录(解绑)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function logout(Request $request, Response $response)
    {
        try {
            $openId = $this->ci['open_id'];
            $userInfo = $this->ci['user_info'];
            if (empty($openId) || empty($userInfo['user_id'])) {
                throw new RunTimeException(['invalid_data']);
            }
            AgentService::miniAppLogout($openId, $userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 登录验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function loginSmsCode(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $mobile      = $params['mobile'];
            $countryCode = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
            // 1.验证是否已存在代理手机号
            $agentInfo   = AgentModel::getByMobile($mobile, $countryCode);
            if (empty($agentInfo)) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $errorCode = CommonServiceForApp::sendValidateCode($mobile, CommonServiceForApp::SIGN_WX_STUDENT_APP, $countryCode);
            if (!empty($errorCode)) {
                $result = Valid::addAppErrors([], $errorCode);
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 成为代理申请验证码
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function applicationCode(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $mobile      = $params['mobile'];
            $countryCode = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
            // 1.验证是否已存在代理手机号
            if (AgentService::checkAgentExists($mobile, $countryCode)) {
                throw new RunTimeException(['agent_have_exist']);
            }
            // 2.验证是否已存在申请
            if (AgentService::checkAgentApplicationExists($mobile, $countryCode)) {
                throw new RunTimeException(['agent_application_exists']);
            }
            $errorCode = CommonServiceForApp::sendValidateCode($mobile, CommonServiceForApp::SIGN_WX_STUDENT_APP, $countryCode);
            if (!empty($errorCode)) {
                $result = Valid::addAppErrors([], $errorCode);
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 提交成为代理申请
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function application(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key'        => 'sms_code',
                'type'       => 'required',
                'error_code' => 'validate_code_error'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        // 验证手机验证码
        if (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params['mobile'], $params['sms_code'], $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE)) {
            return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
        }
        try {
            AgentService::addApplication($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}