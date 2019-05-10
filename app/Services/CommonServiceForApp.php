<?php
/**
 * Created by PhpStorm.
 * User: dahua
 * Date: 2019/4/29
 * Time: 16:50
 */


namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\NewSMS;
use App\Libs\RedisDB;

class CommonServiceForApp
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;

    const SIGN_TEACHER_APP = '小叶子爱练琴';
    const SIGN_STUDENT_APP = '小叶子爱练琴';
    const SIGN_WX_TEACHER_APP = '小叶子爱练琴';
    const SIGN_WX_STUDENT_APP = '小叶子爱练琴';

    /**
     * 发送短信验证码
     * 有效期5分钟
     * 重复发送间隔1分钟
     *
     * @param string $mobile 手机号
     * @param string $sign 短信签名
     * @return null|string
     */
    public static function sendValidateCode($mobile, $sign)
    {
        $redis = RedisDB::getConn();
        $sendTimeCacheKey = self::VALIDATE_CODE_TIME_CACHE_KEY_PRI . $mobile;
        $lastSendTime = $redis->get($sendTimeCacheKey);

        $now = time();
        if (!empty($lastSendTime) && $now - $lastSendTime <= self::VALIDATE_CODE_WAIT_TIME) {
            return 'send_validate_code_in_wait_time';
        }

        $code = (string)rand(1000, 9999);
        $msg = "您好，本次验证码为：".$code."，有效期为五分钟，可以在60秒后重新获取";

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $success = $sms->sendValidateCode($mobile, $msg, $sign);
        if (!$success) {
            return 'send_validate_code_failure';
        }

        $redis = RedisDB::getConn();
        $cacheKey = self::VALIDATE_CODE_CACHE_KEY_PRI . $mobile;
        $redis->setex($cacheKey, self::VALIDATE_CODE_EX, $code);
        $redis->setex($sendTimeCacheKey, self::VALIDATE_CODE_WAIT_TIME, $now);

        return null;
    }

    /**
     * 检查手机验证码
     *
     * @param string $mobile 手机号
     * @param int $code 验证码
     * @return bool
     */
    public static function checkValidateCode($mobile, $code)
    {
        if (empty($mobile) || empty($code)) {
            return false;
        }

        // 超级验证码，可以直接在redis里设置或清空
        $redis = RedisDB::getConn();
        $superCodeCache = DictConstants::get(DictConstants::APP_CONFIG_COMMON, 'super_validate_code');
        if ($superCodeCache == $code) {
            return true;
        }

        // 审核专用账号和验证码
        list($reviewStudentMobile, $reviewValidateCode) = DictConstants::get(DictConstants::APP_CONFIG_COMMON,
            ['review_mobile', 'review_validate_code']);
        if ($mobile == $reviewStudentMobile && $code == $reviewValidateCode) {
            return true;
        }

        $cacheKey = self::VALIDATE_CODE_CACHE_KEY_PRI . $mobile;
        $codeCache = $redis->get($cacheKey);

        if ($codeCache != $code) {
            return false;
        }

        $redis->del($cacheKey);
        return true;
    }

}