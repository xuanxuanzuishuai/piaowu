<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 6:51 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\NewSMS;
use App\Libs\RealDictConstants;

class RealStudentService
{
    /**
     * 真人业务线学生注册
     * @param $mobile
     * @param string $countryCode
     * @param int $channel
     * @param array $referralData
     * @param array $weChatData
     * @param array $loginActiveType
     * @param array $extData
     * @return array|bool|mixed [
     * student_id    number ERP端学员ID
     * is_new    bool    是否是新注册用户
     * uuid    str     全局唯一uuid
     * ]
     * @throws RunTimeException
     */
    public static function register($mobile, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE, $channel = 0, $referralData = [], $weChatData = [], $loginActiveType = 0, $extData = [])
    {
        $registerData = $extData;
        $registerData['app_id'] = Constants::REAL_APP_ID;
        $registerData['mobile'] = $mobile;
        $registerData['country_code'] = $countryCode;
        $registerData['channel_id'] = empty($channel) ? RealDictConstants::get(RealDictConstants::REAL_REFERRAL_CONFIG, 'register_default_channel') : $channel;
        //获取转介绍相关信息
        if (!empty($referralData['qr_id'])) {
            $qrData = MiniAppQrService::getQrInfoById($referralData['qr_id'], ['user_id', 'channel_id', 'user_type']);
            $registerData['referee_id'] = RealReferralService::DEFAULT_REFEREE_ID;
            $registerData['referee_type'] = $qrData['user_type'];
            $registerData['referee_user_id'] = $qrData['user_id'];
            $registerData['channel_id'] = !empty($qrData['channel_id']) ? $qrData['channel_id'] : $channel;
        }
        //微信相关信息
        if (!empty($weChatData['open_id'])) {
            $registerData['open_id'] = $weChatData['open_id'];
            $registerData['user_type'] = $weChatData['user_type'];
            $registerData['busi_type'] = $weChatData['busi_type'];
        }
        //注册用户
        $studentInfo = (new Erp())->refereeStudentRegister($registerData);
        if (empty($studentInfo)) {
            throw new RunTimeException(['user_register_fail']);
        }
        //粒子激活记录
       if (!empty($loginActiveType) && ($studentInfo['is_new'] == false)) {
           StudentService::studentLoginActivePushQueue($registerData['app_id'], $studentInfo['student_id'], $loginActiveType, $registerData['channel_id']);
       }
        return $studentInfo;
    }
}