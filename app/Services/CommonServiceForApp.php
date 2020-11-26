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
use App\Models\CountryCodeModel;

class CommonServiceForApp
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;
    const INT_VALIDATE_CODE_EX = 600;
    const INT_VALIDATE_CODE_WAIT_TIME = 120;
    const DEFAULT_COUNTRY_CODE = '86';

    const SIGN_TEACHER_APP = '小叶子';
    const SIGN_STUDENT_APP = '小叶子';
    const SIGN_WX_TEACHER_APP = '小叶子';
    const SIGN_WX_STUDENT_APP = '小叶子';
    const SIGN_AI_PEILIAN = '小叶子智能陪练';

    /**
     * 发送短信验证码
     * 有效期5分钟
     * 重复发送间隔1分钟
     *
     * @param string $mobile 手机号
     * @param string $sign 短信签名
     * @param string $countryCode
     * @return null|string
     */
    public static function sendValidateCode($mobile, $sign, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        if (empty($countryCode)) {
            $countryCode = NewSMS::DEFAULT_COUNTRY_CODE;
        }
        $redis = RedisDB::getConn();
        $cacheKey = self::VALIDATE_CODE_CACHE_KEY_PRI . $countryCode . $mobile;
        $sendTimeCacheKey = self::VALIDATE_CODE_TIME_CACHE_KEY_PRI . $countryCode . $mobile;
        $lastSendTime = $redis->get($sendTimeCacheKey);

        $now = time();
        if (!empty($lastSendTime) && $now - $lastSendTime <= self::VALIDATE_CODE_WAIT_TIME) {
            return 'send_validate_code_in_wait_time';
        }

        $code = (string)rand(1000, 9999);
        $msg = "您好，本次验证码为：".$code."，有效期为五分钟，可以在60秒后重新获取";

        $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
        $success = $sms->sendValidateCode($mobile, $msg, $sign, $countryCode);
        if (!$success) {
            return 'send_validate_code_failure';
        }

        $redis->setex($cacheKey, self::VALIDATE_CODE_EX, $code);
        $redis->setex($sendTimeCacheKey, self::VALIDATE_CODE_WAIT_TIME, $now);

        return null;
    }

    /**
     * 检查手机验证码
     *
     * @param string $mobile 手机号
     * @param int $code 验证码
     * @param $countryCode
     * @return bool
     */
    public static function checkValidateCode($mobile, $code, $countryCode = NewSMS::DEFAULT_COUNTRY_CODE)
    {
        if (empty($countryCode)) {
            $countryCode = NewSMS::DEFAULT_COUNTRY_CODE;
        }

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

        $cacheKey = self::VALIDATE_CODE_CACHE_KEY_PRI . $countryCode . $mobile;
        $countKey = $cacheKey . '_count';

        $codeCache = $redis->get($cacheKey);
        if (empty($codeCache)) {
            return false;
        }

        if ($codeCache != $code) {
            // 错误大于5次删除验证码
            $count = $redis->get($countKey);
            if ($count >= 5) {
                $redis->del([$countKey, $cacheKey]);
            } else {
                $redis->incr($countKey);
                $redis->expire($countKey, self::VALIDATE_CODE_EX);
            }

            return false;
        }

        $redis->del([$countKey, $cacheKey]);
        return true;
    }

    public static function getCountryCode()
    {
        $countryCodeData = CountryCodeModel::getAll();
        return $countryCodeData ?? [];
    }
}