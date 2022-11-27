<?php


namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Models\BAApplyModel;
use App\Services\ShopService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Services\WechatTokenService;
use App\Libs\Valid;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Wx extends ControllerBase
{

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


        $boundInfo = BAApplyModel::getRecord(['open_id' => $openId]);


        // 没有找到该openid的绑定关系
        if (empty($boundInfo)) {
            return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
        }

        $token = WechatTokenService::generateToken($boundInfo['ba_id'],$openId);


        return HttpHelper::buildResponse($response, [
            'token' => $token,
            'ba_id' => $boundInfo['ba_id']
        ]);
    }


    /**
     * 门店列表
     * @param Request $request
     * @param Response $response
     * @return Response|static
     */
    public function shopList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {

            list($page, $count) = Util::formatPageCount($params);
            list($list, $totalCount) = ShopService::getShopList($params, $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'shop_list' => $list,
            'total_count' => $totalCount
        ], StatusCode::HTTP_OK);
    }

    public function apply(Request $request, Response $response)
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
                $data['openid'],
                $info['uuid']
            );
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        // 上报设备信息
        try {
            (new DeviceCommonTrackTopic)->pushLogin([
                'from'         => DeviceCommonTrackTopic::FROM_TYPE_WX,
                'channel_id'   => $channelId,
                'open_id'      => $data['openid'] ?? '',
                'uuid'         => $info['uuid'] ?? '',
                'new_user'     => intval($info['is_new']),    // 0老用户，1新用户
                'anonymous_id' => $request->getHeader('anonymous_id')[0] ?? '',   // 埋点匿名id, 投放页有
                'mobile'       => $params['mobile'],
                'union_id'     => WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE)->getUnionid($data['openid'] ?? ''),
            ])->publish();
        } catch (\Exception $e) {
            SimpleLogger::info('push_login_err', ['msg' => 'wx_student_register', 'err' => $e->getMessage()]);
        }
        return HttpHelper::buildResponse($response, ['token' => $token,'is_new' => $info['is_new'] ?? 0, 'uuid' => $info['uuid']]);
    }

}