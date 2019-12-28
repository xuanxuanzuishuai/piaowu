<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\ReferralModel;
use App\Models\StudentLandingModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\UserWeixinModel;

class StudentServiceForWeb
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;

    // 体验类型
    const TRIAL_TYPE_NORMAL = 1; // 普通用户
    const TRIAL_TYPE_LEADS = 2; // 熊猫leads

    // 体验时长(天)
    const TRIAL_DAYS_NORMAL = 7;
    const TRIAL_DAYS_LEADS = 21;

    // 用户操作类型 观看付费服务介绍
    const ACTION_READ_SUB_INFO = 'act_sub_info';

    /**
     * 手机验证码登录
     *
     * @param string $mobile 手机号
     * @param int $code 短信验证码
     * @param null $referrerMobile 介绍人手机号
     * @return array [0]errorCode [1]登录数据
     * @throws RunTimeException
     */
    public static function register($mobile, $code, $referrerMobile = null)
    {
        // 检查验证码
        if (!CommonServiceForApp::checkValidateCode($mobile, $code)) {
            throw new RunTimeException(['validate_code_error']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (!empty($student)) {
            throw new RunTimeException(['mobile_has_been_registered']);
        }

        $newStudent = StudentServiceForApp::studentRegister($mobile, StudentModel::CHANNEL_WEB_REGISTER);
        if (empty($newStudent)) {
            throw new RunTimeException(['student_register_fail']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (empty($student)) {
            throw new RunTimeException(['unknown_student']);
        }

        if (!empty($referrerMobile)) {
            $referrer = StudentModelForApp::getStudentInfo(null, $referrerMobile);
            if (empty($referrer)) {
                throw new RunTimeException(['referrer_not_exist']);
            } else {
                ReferralService::addReferral($referrer['id'],
                    $student['id'],
                    ReferralModel::REFERRAL_TYPE_WX_SHARE);
            }
        }

        $studentData = [
            'id' => $student['id'],
            'uuid' => $student['uuid']
        ];

        return $studentData;
    }

    /**
     * 获取介绍人信息
     * @param $mobile
     * @return array
     * @throws RunTimeException
     */
    public static function referrerInfo($mobile)
    {
        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (empty($student)) {
            throw new RunTimeException(['unknown_student']);
        }

        $referrer = [
            'id' => $student['id'],
            'uuid' => $student['uuid'],
            'student_name' => $student['name'],
            'avatar' => Util::getQiNiuFullImgUrl($student['thumb']),
            'mobile' => $student['mobile'],
        ];

        return $referrer;
    }

    /**
     * 用户登录
     * @param $mobile
     * @param $code
     * @param $channelId
     * @param $adId
     * @param $callback
     * @param $referrerURL
     * @param $wxCode
     * @param $clickId
     * @return array
     * @throws RunTimeException
     */
    public static function mobileLogin($mobile, $code, $channelId, $adId, $callback, $referrerURL = null, $wxCode = null, $clickId = null)
    {
        // 检查验证码
        if (!CommonServiceForApp::checkValidateCode($mobile, $code)) {
            throw new RunTimeException(['validate_code_error']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (!empty($student)) {
            return $student;
        }

        $newStudent = StudentServiceForApp::studentRegister($mobile, $channelId);
        if (empty($newStudent)) {
            throw new RunTimeException(['student_register_fail']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (empty($student)) {
            throw new RunTimeException(['unknown_student']);
        }

        // 注册成功，回调第三方
        $info = [
            'user_id' => $student['id'],
            'platform' => TrackService::PLAT_ID_UNKNOWN,
            'ad_channel' => TrackService::CHANNEL_OCEAN_LEADS,
            'ad_id' => $adId,
            'callback' => $callback
        ];
        if (empty($adId) || empty($callback)) {
            $info['ad_channel'] = TrackService::CHANNEL_OTHER;
        }
        TrackService::trackEvent(TrackService::TRACK_EVENT_FORM_COMPLETE, $info, $student['id']);

        $uuid = $student['uuid'];

        //获取openid, 只关注微信投放渠道
        if(!empty($wxCode) && !empty($channelId) && $channelId == DictConstants::get(DictConstants::LANDING_CONFIG, 'channel_weixin')) {
            $data = WeChatService::getWeixnUserOpenIDAndAccessTokenByCode($wxCode, 1, UserWeixinModel::USER_TYPE_STUDENT);
            if(!empty($data) && !empty($data['openid'])) {
                //保存openid支付时使用
                StudentLandingModel::setOpenId($uuid, $data['openid']);
                //注册成功后，反馈给微信广告平台
                $userActionSetId = DictConstants::get(DictConstants::LANDING_CONFIG, 'user_action_set_id');
                $accessToken = WeChatService::getAccessToken(1, UserWeixinModel::USER_TYPE_STUDENT);
                if(!empty($clickId)) {
                    WeChatService::feedback($accessToken, [
                        'user_action_set_id' => $userActionSetId,
                        'action_time'        => time(),
                        'action_type'        => 'RESERVATION',
                        'url'                => $referrerURL,
                        'trace'              => [
                            'click_id' => $clickId,
                        ]
                    ]);
                }
            } else {
                throw new RunTimeException(['can_not_obtain_open_id']);
            }
        }

        return $student;
    }
}