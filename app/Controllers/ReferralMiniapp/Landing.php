<?php
namespace App\Controllers\ReferralMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\UserWeiXinModel;
use App\Services\CommonServiceForApp;
use App\Services\QrInfoService;
use App\Services\Queue\QueueService;
use App\Services\Queue\Track\DeviceCommonTrackTopic;
use App\Services\ReferralService;
use App\Services\ShowMiniAppService;
use App\Services\UserService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Landing extends ControllerBase
{

    /**
     * 0元购 landing页
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $sceneData = ReferralService::getSceneData(urldecode($params['scene'] ?? ''));
            $pageData = ReferralService::getMiniAppIndexData($sceneData, $this->ci['referral_miniapp_openid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 注册
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function register(Request $request, Response $response)
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
            $openid = $this->ci['referral_miniapp_openid'];
            // 获取open id
            $weChat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP);
            $sessionKey = $weChat->getSessionKey($openid, $params['wx_code'] ?? '');
            $sceneData = ReferralService::getSceneData(urldecode($params['scene'] ?? ''));
            $sceneData['app_id'] = ReferralService::REFERRAL_MINI_APP_ID;
            if (!empty($params['wx_code'])) {
                $sceneData['wx_code'] = $params['wx_code'];
            }
            list($openid, $lastId, $mobile, $uuid, $hadPurchased, $isNew) = ReferralService::remoteRegister(
                $openid,
                $params['iv'] ?? '',
                $params['encrypted_data'] ?? '',
                $sessionKey,
                $params['mobile'] ?? '',
                $params['country_code'] ?? '',
                $sceneData['r'] ?? '', // referrer ticket
                $sceneData['c'] ?? '', // channel id
                $sceneData
            );
            //获取分享scene
            // $shareScene = ReferralService::makeReferralMiniShareScene(['id' => $lastId], $sceneData);
            $createShareSceneData = [
                [
                    'user_type'                 => Constants::USER_TYPE_STUDENT,
                    'user_id'                   => $lastId,
                    'channel_id'                => DictConstants::get(DictConstants::STUDENT_INVITE_CHANNEL, 'NORMAL_STUDENT_INVITE_STUDENT'),
                    'no_need_check_activity_id' => true,
                ]
            ];
            $shareScene = QrInfoService::getQrIdList(Constants::SMART_APP_ID, Constants::REAL_MINI_BUSI_TYPE, $createShareSceneData)[0]['qr_id'] ?? '';
            SimpleLogger::info("referral_mini_register", [$shareScene, $lastId, $createShareSceneData]);
            // 上报设备信息
            try {
                (new DeviceCommonTrackTopic)->pushLogin([
                    'from'         => DeviceCommonTrackTopic::FROM_TYPE_MINI_APP,
                    'channel_id'   => $sceneData['c'] ?? '',
                    'open_id'      => $openid,
                    'uuid'         => $uuid,
                    'new_user'     => $isNew,    // 0老用户，1新用户
                    'anonymous_id' => $params['anonymous_id'] ?? '',   // 埋点匿名id, 投放页有
                ])->publish();
            } catch (\Exception $e) {
                SimpleLogger::info('push_login_err', ['msg' => 'landing_mini_app_register', 'err' => $e->getMessage()]);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['openid' => $openid, 'last_id' => $lastId, 'mobile' => $mobile, 'uuid' => $uuid, 'had_purchased' => $hadPurchased,'share_scene' =>$shareScene]);
    }

    /**
     * 投放注册
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function tfregister(Request $request, Response $response)
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
            $openid = $this->ci['referral_miniapp_openid'];
            // 获取open id
            $weChat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP);
            $sessionKey = $weChat->getSessionKey($openid, $params['wx_code'] ?? '');
            parse_str($params['param_arr'], $paramArr);
            $channelId = $paramArr['channel_id'] ?? DictConstants::get(DictConstants::TOU_FANG, 'mini_tou_fang_default_channel');
            $extParams['app_id'] = ReferralService::REFERRAL_MINI_APP_ID;
            if (!empty($params['wx_code'])) {
                $extParams['wx_code'] = $params['wx_code'];
            }
            list($openid, $lastId, $mobile, $uuid, $hadPurchased, $isNew) = ReferralService::remoteRegister(
                $openid,
                $params['iv'] ?? '',
                $params['encrypted_data'] ?? '',
                $sessionKey,
                $params['mobile'] ?? '',
                $params['country_code'] ?? '',
                 '', // referrer ticket
                $channelId, // channel id
                $extParams
            );

            //投放回传
            //channel_id,callback,ref,user_id
            if ($isNew) {
                QueueService::formRegister(array_merge([
                    'user_id' => $lastId,
                    'ref' => $params['path'] . '?' . http_build_query($paramArr),
                    'ad_channel' => 35,
                ], $paramArr));
            }

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['openid' => $openid, 'last_id' => $lastId, 'mobile' => $mobile, 'uuid' => $uuid, 'had_purchased' => $hadPurchased]);
    }

    /**
     * 获取前50人购买姓名
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function buyName(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $data = ReferralService::getBuyUserName($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 小程序已购买转介绍海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function buyPageReferralPoster(Request $request, Response $response)
    {
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 练琴测评3.0落地页

     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function playReview(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $sceneData = ReferralService::getSceneData(urldecode($params['scene'] ?? ''));
            $pageData = ShowMiniAppService::getMiniAppPlayReviewData($sceneData, $this->ci['referral_miniapp_openid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 助教老师信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function assistantInfo(Request $request, Response $response)
    {
        try {
            $data = ReferralService::assistantInfo($this->ci['referral_miniapp_openid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * DSS - 获取学生是否能够购买指定课包，如果系统判定的重复用户购买指定课包时会返回其他课包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function checkStudentIsRepeat(Request $request, Response $response)
    {
        $rules  = [
            [
                'key'        => 'pkg',
                'type'       => 'required',
                'error_code' => 'pkg_is_required'
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = UserService::getDssStudentRepeatBuyPkg($params['uuid'], $params['pkg']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
