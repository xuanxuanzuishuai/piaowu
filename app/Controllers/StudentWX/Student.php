<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/23
 * Time: 15:41
 */

namespace App\Controllers\StudentWX;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Services\ReferralActivityService;
use App\Services\UserService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\CommonServiceForApp;


class Student extends ControllerBase
{

    /** 注册并绑定
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function register(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'referee_type',
                'type' => 'integer'
            ],
            [
                'key' => 'referee_id',
                'type' => 'integer'
            ],
            [
                'key' => 'wx_code',
                'type' => 'required',
                'error_code' => 'wx_code_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $oldToken = $request->getHeader('token');
        $oldToken = $oldToken[0] ?? null;
        if (!empty($oldToken)) {
            WechatTokenService::deleteToken($oldToken);
        }

        try {
            $appId = $params['app_id'] ?? NULL;
            if (empty($appId)) {
                throw new RunTimeException(['need_app_id']);
            }
            if (empty($params['sms_code']) && empty($params['password'])) {
                return $response->withJson(Valid::addAppErrors([], 'please_check_the_parameters'), StatusCode::HTTP_OK);
            } elseif (!empty($params['sms_code']) && !CommonServiceForApp::checkValidateCode($params["mobile"], $params["sms_code"], $params['country_code'])) {
                return $response->withJson(Valid::addAppErrors([], 'incorrect_mobile_phone_number_or_verification_code'), StatusCode::HTTP_OK);
            } elseif (!empty($params['password']) && !CommonServiceForApp::checkPassword($params['mobile'], $params['password'], $params['country_code'])) {
                return $response->withJson(Valid::addAppErrors([], 'password_error'), StatusCode::HTTP_OK);
            }
            $arr = [
                Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
            ];
            $busiType = $arr[$appId] ?? Constants::SMART_WX_SERVICE;

            $data = WeChatMiniPro::factory($appId, $busiType)->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);
            if (empty($data) || empty($data['openid'])) {
                throw new RunTimeException(['can_not_obtain_open_id']);
            }
            $userType = Constants::USER_TYPE_STUDENT;
            $channelId = $params['channel_id'] ?? Constants::CHANNEL_WE_CHAT_SCAN;
            UserService::studentRegisterBound($appId, $params['mobile'], $channelId, $data['openid'], $busiType, $userType, $params["referee_id"]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /** token失效时获取token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {
        $old_token = $this->ci["token"];
        if (!empty($old_token)){
            WechatTokenService::deleteToken($old_token);
        }

        $openId = $this->ci["open_id"];
        if (empty($openId)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $boundInfo = UserService::getUserWeiXinInfo($this->ci["app_id"], $openId, $this->ci['user_type'], $this->ci['busi_type']);

        // 没有找到该openid的绑定关系
        if (empty($boundInfo)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WechatTokenService::generateToken($boundInfo["user_id"], $this->ci['user_type'],
            $this->ci["app_id"], $openId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["token" => $token]
        ], StatusCode::HTTP_OK);
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

    /**
     * 获取员工活动海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPosterList(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'employee_id',
                'type'       => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getPosterList(
                $this->ci['user_info']['user_id'],
                $params['channel'] ?? DictConstants::get(DictConstants::EMPLOYEE_ACTIVITY_ENV, 'invite_channel'),
                $params['activity_id'],
                $params['employee_id'],
                $this->ci['user_info']['app_id']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }
}
