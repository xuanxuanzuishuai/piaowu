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
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\DictModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\MiniAppQrService;
use App\Services\Queue\Track\DeviceCommonTrackTopic;
use App\Services\ReferralActivityService;
use App\Services\ReferralService;
use App\Services\ShowMiniAppService;
use App\Services\StudentService;
use App\Services\StudentServices\DssStudentService;
use App\Services\UserService;
use App\Services\WechatService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\CommonServiceForApp;


class Student extends ControllerBase
{

    /** 注册/登录 并绑定
     * wechat && web
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

        empty($params['country_code']) && $params['country_code'] = NewSMS::DEFAULT_COUNTRY_CODE;
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

            if (!empty($params['wx_code'])) {
                $data = WeChatMiniPro::factory($appId, $busiType)->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);
                if (empty($data) || empty($data['openid'])) {
                    throw new RunTimeException(['can_not_obtain_open_id']);
                }
            } else {
                $data['openid'] = NULL;
            }

            $userType = Constants::USER_TYPE_STUDENT;
            $channelId = $params['channel_id'] ?? Constants::CHANNEL_WE_CHAT_SCAN;
            $sceneData = ReferralActivityService::getParamsInfo($params['param_id']);
            if (!empty($sceneData['c'])) {
                $channelId = $sceneData['c'];
            }
            $info = UserService::studentRegisterBound($appId, $params['mobile'], $channelId, $data['openid'], $busiType, $userType, $params["referee_id"]);
            if (empty($info['is_new'])) {
                StudentService::studentLoginActivePushQueue($appId, $info['student_id'], Constants::DSS_STUDENT_LOGIN_TYPE_WX, $channelId);
            }
            $token = WechatTokenService::generateToken(
                $info['student_id'],
                DssUserWeiXinModel::USER_TYPE_STUDENT,
                $appId,
                $data['openid']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        // 上报设备信息
        try {
            $studentInfo = DssStudentModel::getRecord(['id' => $info['student_id']]);
            (new DeviceCommonTrackTopic)->pushLogin([
                'from'         => DeviceCommonTrackTopic::FROM_TYPE_WX,
                'channel_id'   => $channelId,
                'open_id'      => $data['openid'] ?? '',
                'uuid'         => $studentInfo['uuid'] ?? '',
                'new_user'     => $info['is_new'] ?? 0,    // 0老用户，1新用户
                'anonymous_id' => $request->getHeader('anonymous_id')[0] ?? '',   // 埋点匿名id, 投放页有
                'mobile'       => $params['mobile'],
            ])->publish();
        } catch (\Exception $e) {
            SimpleLogger::info('push_login_err', ['msg' => 'wx_student_register', 'err' => $e->getMessage()]);
        }
        return HttpHelper::buildResponse($response, ['token' => $token,'is_new' => $info['is_new'] ?? 0, 'uuid' => $info['uuid']]);
    }

    /** token失效时获取token
     * wechat
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
        $params = $request->getParams();
        $channelId = $params['channel_id'] ?? 0;
        StudentService::studentLoginActivePushQueue($this->ci["app_id"], $boundInfo['user_id'], Constants::DSS_STUDENT_LOGIN_TYPE_WX, $channelId);
        $student = DssStudentModel::getRecord(['id' => $boundInfo['user_id']]);
        // 上报设备信息
        try {
            (new DeviceCommonTrackTopic)->pushLogin([
                'from'         => DeviceCommonTrackTopic::FROM_TYPE_WX,
                'channel_id'   => $channelId,
                'open_id'      => $openId,
                'uuid'         => $student['uuid'] ?? '',
                'new_user'     => 0,    // 0老用户，1新用户
                'anonymous_id' => $request->getHeader('anonymous_id')[0] ?? '',   // 埋点匿名id, 投放页有
            ])->publish();
        } catch (\Exception $e) {
            SimpleLogger::info('push_login_err', ['msg' => 'wx_student_login', 'err' => $e->getMessage()]);
        }
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ["token" => $token, 'uuid' => $student['uuid']]
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

        // 账户粒子激活
        $channelId = $params['channel_id'] ?? 0;
        if (empty($channelId) && !empty($params['scene'])) {
            $channelId = ShowMiniAppService::getSceneData(urldecode($params['scene'] ?? ''))['channel_id'] ?? 0;
        }
        StudentService::mobileSendSMSCodeActive(Constants::SMART_APP_ID, $params['mobile'], Constants::DSS_STUDENT_LOGIN_TYPE_WX, $channelId);

        empty($params['country_code']) &&  $params['country_code']=NewSMS::DEFAULT_COUNTRY_CODE;
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

    /**
     * 用户的基本信息，目前仅提供埋点用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function accountDetail(Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        $data = [];
        if ($this->ci['user_info']['app_id'] == Constants::SMART_APP_ID) {
            $data = DssStudentModel::getRecord(['id' => $studentId], ['uuid','mobile']);
        }
        $studentStatus = (new Dss())->getStudentIdentity(['student_id' => $studentId]);
        $data['student_status'] = $studentStatus['student_status'];
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 其他系统的token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getOtherToken(Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        if ($this->ci['user_info']['app_id'] == Constants::SMART_APP_ID) {
            $data = (new Dss())->getToken(['student_id' => $studentId]);
        }
        return HttpHelper::buildResponse($response, $data);
    }

    public function menuTest(Request $request, Response $response)
    {
        $params   = $request->getParams();
        $appId    = $params['appid'] ?? Constants::SMART_APP_ID;
        $busiType = $params['busi_type'] ?? DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER;
        $wechat   = WeChatMiniPro::factory($appId, $busiType);
        $res = '';
        if (method_exists($wechat, $params['function'])) {
            $res = call_user_func_array([$wechat, $params['function']], $params['params'] ?? []);
        }
        if ($params['function'] == 'delCache') {
            $res = DictModel::delCache($params['type'], 'dict_list_');
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 公众号菜单按钮跳转
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function menuRedirect(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'code',
                'type'       => 'required',
                'error_code' => 'code_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $config = DictConstants::get(DictConstants::WECHAT_CONFIG, 'menu_redirect');
        $config = json_decode($config, true);
        if (empty($config[$params['code']])) {
            return $response->withStatus(StatusCode::HTTP_NOT_FOUND);
        }
        return $response->withRedirect($config[$params['code']]);
    }

    /**
     * 邀请列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function inviteList(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key' => 'referrer_uuid',
                    'type' => 'required',
                    'error_code' => 'referrer_uuid_is_required'
                ]
            ];
            $params = $request->getParams();
            $result = Valid::Validate($params, $rules);
            if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }

            list($page, $count) = Util::formatPageCount($params);
            $data = ReferralService::myInviteStudentList($params, $page, $count);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function broadcast(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $data = ReferralService::getBroadcastData($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取学生助教信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getStudentAssistantInfo(Request $request, Response $response)
    {
        try {
            $studentId = $this->ci['user_info']['user_id'];
            $assistantInfo = DssStudentService::getStudentAssistantInfo($studentId, ['wx_nick', 'wx_thumb', 'wx_num', 'wx_qr']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $assistantInfo);
    }
}
