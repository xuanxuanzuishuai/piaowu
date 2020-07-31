<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\ReferralModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;

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
     * @param $countryCode
     * @return array [0]errorCode [1]登录数据
     * @throws RunTimeException
     */
    public static function register($mobile, $code, $referrerMobile = null, $countryCode)
    {
        // 检查验证码
        if (!CommonServiceForApp::checkValidateCode($mobile, $code, $countryCode)) {
            throw new RunTimeException(['validate_code_error']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (!empty($student)) {
            throw new RunTimeException(['mobile_has_been_registered']);
        }

        list($newStudent) = StudentServiceForApp::studentRegister($mobile, StudentModel::CHANNEL_WEB_REGISTER, $countryCode);
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
     * @param $adChannel
     * @param $adParams
     * @param $refereeId
     * @param $countryCode
     * @return array
     * @throws RunTimeException
     */
    public static function mobileLogin($mobile, $code, $channelId, $adChannel, $adParams, $refereeId, $countryCode)
    {
        // 检查验证码
        if (!CommonServiceForApp::checkValidateCode($mobile, $code, $countryCode)) {
            throw new RunTimeException(['validate_code_error']);
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        if (!empty($student)) {
            return $student;
        }

        list($newStudent) = StudentServiceForApp::studentRegister(
            $mobile,
            $channelId,
            NULL,
            $refereeId,
            $countryCode
        );
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
            'ad_channel' => $adChannel,
            'mobile' => $mobile,
        ];

        $info = array_merge($info, $adParams);

        TrackService::trackEvent(TrackService::TRACK_EVENT_FORM_COMPLETE, $info, $student['id']);

        return $student;
    }
}