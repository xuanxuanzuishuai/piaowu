<?php
namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\FreeCodeLogModel;
use App\Services\CommonServiceForApp;
use App\Services\Queue\QueueService;
use App\Services\Queue\Track\DeviceCommonTrackTopic;
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\WebLandingService;
use I18N\Lang;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;

class Landing extends ControllerBase
{

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function index(Request $request, Response $response)
    {
        try {
            $params  = $request->getParams();
            $channel = $params['channel_id'] ?? 0;
            $flag    = WebLandingService::checkChannel($channel);
            $pageData['can_give'] = $flag;
            $pageData['msg'] = '';
            if (!$flag) {
                $pageData['msg'] = Lang::getWord('event_pass_deadline');
            }
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
     */
    public function register(Request $request, Response $response)
    {
        $params = $request->getParams();
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
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $appId       = $params['app_id'] ?? Constants::SMART_APP_ID;
            $busiType    = $params['busi_type'] ?? DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER;
            $userType    = DssUserWeiXinModel::USER_TYPE_STUDENT;
            $channelId   = $params['channel_id'] ?? 0;
            $mobile      = $params['mobile'];
            $countryCode = $params['country_code'] ?? NewSMS::DEFAULT_COUNTRY_CODE;
            // 验证手机验证码
            if (!CommonServiceForApp::checkValidateCode($mobile, $params['sms_code'], $countryCode)) {
                return $response->withJson(Valid::addAppErrors([], 'validate_code_error'), StatusCode::HTTP_OK);
            }
            $registerTime = 0;
            $word     = 'give_5days_code';
            $flag     = WebLandingService::checkChannel($channelId);
            $give     = true;
            $userId   = '';
            $uuid     = '';
            $openId   = $params['open_id'] ?? '';
            $isNewUser = 0; // 是否是新注册的用户
            if (!$flag) {
                $word = 'event_pass_deadline';
                $give = false;
            }
            $student = DssStudentModel::getRecord(
                ['mobile' => $mobile, 'country_code' => $countryCode],
                ['id', 'uuid', 'channel_id', 'create_time']
            );
            if (empty($student)) {
                if (empty($openId) && !empty($params['wx_code'])) {
                    $wx = WeChatMiniPro::factory($appId, $busiType);
                    $info = $wx->getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code']);
                    if (!empty($info['openid'])) {
                        $openId = $info['openid'];
                    }
                }
                $info = UserService::studentRegisterBound($appId, $mobile, $channelId, $openId, $busiType, $userType);
                if (empty($info['student_id'])) {
                    throw new RunTimeException(['user_register_fail']);
                }
                $userId       = $info['student_id'];
                $uuid         = $info['uuid'];
                $registerTime = time();
                $isNewUser = 1;
            } elseif ($give) {
                $userId       = $student['id'];
                $uuid         = $student['uuid'];
                $channelId    = $student['channel_id'];
                $registerTime = $student['create_time'];
                $giftCode     = DssGiftCodeModel::getRecord(['buyer' => $userId]);
                if (!empty($giftCode)) {
                    $give = false;
                    $word = 'only_new_user_allowed';
                }
                // 用传过来的渠道，不用学生注册渠道
                StudentService::studentLoginActivePushQueue($appId, $student['id'], Constants::DSS_STUDENT_LOGIN_TYPE_H5, $params['channel_id'] ?? 0);

            }
            if ($give && !empty($uuid)) {
                $studentStatus = StudentService::dssStudentStatusCheck($userId);
                QueueService::giftDuration($uuid, DssGiftCodeModel::APPLY_TYPE_AUTO, 5, DssGiftCodeModel::BUYER_TYPE_STUDENT);
                $logData = [
                    'user_id' => $userId,
                    'user_uuid' => $uuid,
                    'create_time' => time()
                ];
                FreeCodeLogModel::insertRecord($logData);
            }
            $data = [
                'give'       => $give,
                'uuid'       => $uuid,
                'msg'        => Lang::getWord($word),
                'channel_id' => $channelId,
                'student_status' => $studentStatus['student_status'] ?? 0,
                'register_time'  => $registerTime,
            ];
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        // 上报设备信息
        try {
            (new DeviceCommonTrackTopic)->pushLogin([
                'from'         => DeviceCommonTrackTopic::FROM_TYPE_H5,
                'channel_id'   => $channelId,
                'open_id'      => $openId ?? '',
                'uuid'         => $student['uuid'] ?? '',
                'new_user'     => $isNewUser ?? 0,    // 0老用户，1新用户
                'anonymous_id' => $request->getHeader('anonymous_id')[0] ?? '',   // 埋点匿名id, 投放页有
            ])->publish();
        } catch (\Exception $e) {
            SimpleLogger::info('push_login_err', ['msg' => 'studentWeb_h5_register', 'err' => $e->getMessage()]);
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
