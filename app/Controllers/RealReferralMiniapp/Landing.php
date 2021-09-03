<?php


namespace App\Controllers\RealReferralMiniapp;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Referral;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Erp\ErpStudentModel;
use App\Services\MiniAppQrService;
use App\Services\RealReferralService;
use App\Services\WechatTokenService;
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
            $openid     = $this->ci['real_referral_miniapp_openid'];
            $appId      = Constants::REAL_APP_ID;
            $busiType   = Constants::REAL_MINI_BUSI_TYPE;
            $userType   = Constants::USER_TYPE_STUDENT;
            $weChat     = WeChatMiniPro::factory($appId, $busiType);
            $sessionKey = $weChat->getSessionKey($openid, $params['wx_code'] ?? '');
            //解密用户手机号
            $jsonMobile = RealReferralService::decodeMobile($params['iv'], $params['encrypted_data'], $sessionKey);
            if (empty($jsonMobile)) {
                throw new RunTimeException(['authorization_error']);
            }
            $mobile      = $jsonMobile['purePhoneNumber'];
            $countryCode = $jsonMobile['countryCode'];
            //查询账号是否存在
            $studentInfo = ErpStudentModel::getRecord(['mobile' => $mobile]);
            $isNew       = false;
            //默认渠道
            $channel = DictConstants::get(DictConstants::REAL_REFERRAL_CONFIG, 'register_default_channel');
            if (empty($studentInfo)) {
                $isNew = true;
                //获取转介绍相关信息
                if (!empty($params['qr_id'])) {
                    $qrData    = MiniAppQrService::getQrInfoById($params['qr_id'], ['user_id', 'channel_id']);
                    $refereeId = $qrData['user_id'];
                    $channel   = !empty($qrData['channel_id']) ? $qrData['channel_id'] : $channel;

                }
                $registerData = [
                    'app_id'       => $appId,
                    'busi_type'    => $busiType,
                    'open_id'      => $openid,
                    'mobile'       => $mobile,
                    'channel_id'   => $channel,
                    'country_code' => $countryCode,
                    'user_type'    => $userType
                ];
                //注册用户
                $studentInfo = (new Erp())->refereeStudentRegister($registerData);
                if (empty($studentInfo)) {
                    throw new RunTimeException(['user_register_fail']);
                }
                $studentInfo['id'] = $studentInfo['student_id'];
                //建立转介绍关系
                if (!empty($refereeId)) {
                    (new Referral())->setReferralUserReferee([
                        'referee_id' => $refereeId,
                        'user_id'    => $studentInfo['id'],
                        'type'       => Constants::USER_TYPE_STUDENT,
                        'app_id'     => $appId,

                    ]);
                }
            }
            //生成token
            $token = WechatTokenService::generateToken($studentInfo['id'], $userType, $appId, $openid);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'is_new'     => $isNew,
            'openid'     => $openid,
            'token'      => $token,
            'mobile'     => $mobile,
            'uuid'       => $studentInfo['uuid'],
            'student_id' => $studentInfo['id']
        ]);
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


}