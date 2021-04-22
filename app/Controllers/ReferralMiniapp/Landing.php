<?php
namespace App\Controllers\ReferralMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\UserCenter;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\UserWeiXinModel;
use App\Services\CommonServiceForApp;
use App\Services\ReferralService;
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
            $pageData = ReferralService::getMiniAppIndexData($sceneData, $this->ci['referral_landing_openid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $pageData);
    }

    /**
     * 注册
     *
     * @param Request $request
     * @param Response $response
     * @return Response
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
            $sessionKey = $weChat->getSessionKey($openid);
            $sceneData = ReferralService::getSceneData(urldecode($params['scene'] ?? ''));
            list($openid, $lastId, $mobile, $uuid, $hadPurchased) = ReferralService::remoteRegister(
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
            $shareScene = ReferralService::makeReferralMiniShareScene(['id' => $lastId], $sceneData);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['openid' => $openid, 'last_id' => $lastId, 'mobile' => $mobile, 'uuid' => $uuid, 'had_purchased' => $hadPurchased,'share_scene' =>$shareScene]);
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
            $data = ReferralService::getBuyUserName();
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }



}
