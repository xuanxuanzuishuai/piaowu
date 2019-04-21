<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/21
 * Time: 12:14
 */

namespace App\Services;


use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AppConfigModel;
use App\Models\StudentModelForApp;
use App\Models\TeacherModelForApp;


class TeacherForAppService
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;

    /**
     * token登录
     *
     * @param string $mobile 手机号
     * @param string $token 登录返回的token
     * @return array [0]errorCode [1]登录数据
     */
    public static function loginWithToken($mobile, $token)
    {
        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        $cacheToken = StudentModelForApp::getStudentToken($student['id']);

        if (empty($cacheToken) || $cacheToken != $token) {
            return ['invalid_token'];
        }

        $loginData = [
            'id' => $student['id'],
            'uuid' => $student['uuid'],
            'studentname' => $student['name'],
            'avatar' => Util::getQiNiuFullImgUrl($student['thumb']),
            'mobile' => $student['mobile'],
            'sub_status' => $student['sub_status'],
            'sub_start_date' => $student['sub_start_date'],
            'sub_end_date' => $student['sub_end_date'],
            'config' => '{}',
            'token' => $token
        ];

        return [null, $loginData];
    }

    public static function registerTeacherInUserCenter($name, $mobile, $uuid = '', $birthday = '', $gender = '')
    {
        $userCenter = new UserCenter(13, 'b56a214222a8420e');
        $authResult = $userCenter->teacherAuthorization($mobile, $name, $uuid, $birthday, $gender);
        return $authResult;
    }

    /**
     * @param $mobile
     * @param $name
     * @return array|null
     */
    public static function teacherRegister($mobile, $name)
    {
        $result = self::registerTeacherInUserCenter($name, $mobile);
        if (empty($result['uuid'])) {
            SimpleLogger::info(__FILE__ . __LINE__, $result);
            return null;
        }

        $uuid = $result['uuid'];
        $lastId = self::addTeacher($mobile, $uuid, $name);

        if (empty($lastId)) {
            SimpleLogger::info(__FILE__ . __LINE__, [
                'msg' => 'user reg error, add new user error.',
            ]);
            return null;
        }

        return $lastId;
    }

    /**
     * @param $mobile
     * @param $uuid
     * @param $name
     * @return int|mixed|null|string
     */
    public static function addTeacher($mobile, $uuid, $name)
    {
        $user = [
            'uuid'           => $uuid,
            'mobile'         => $mobile,
            'name'           => $name,
            'create_time'    => time(),
            'status'     => TeacherModelForApp::ENTRY_REGISTER,
            'is_export'  => 0,
        ];

        $id = TeacherModelForApp::insertRecord($user);

        return $id == 0 ? null : $id;
    }

    /**
     * 发送短信验证码
     * 有效期5分钟
     * 重复发送间隔1分钟
     *
     * @param string $mobile 手机号
     * @return null|string
     */
    public static function sendValidateCode($mobile)
    {
        $redis = RedisDB::getConn();
        $sendTimeCacheKey = self::VALIDATE_CODE_TIME_CACHE_KEY_PRI . $mobile;
        $lastSendTime = $redis->get($sendTimeCacheKey);

        $now = time();
        if (!empty($lastSendTime) && $now - $lastSendTime <= self::VALIDATE_CODE_WAIT_TIME) {
            return 'send_validate_code_in_wait_time';
        }

        $code = rand(1000, 9999);
        $sms = new NewSMS(AppConfigModel::get(AppConfigModel::SMS_URL_CACHE_KEY),
            AppConfigModel::get(AppConfigModel::SMS_API_CACHE_KEY));
        $success = $sms->sendValidateCode($mobile, $code);
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
        $superCodeCache = $redis->get('SUPER_VALIDATE_CODE');
        if ($superCodeCache == $code) {
            return true;
        }

        // 审核专用账号和验证码
        $reviewStudentMobile = AppConfigModel::get('REVIEW_USER_MOBILE');
        $reviewValidateCode = AppConfigModel::get('REVIEW_VALIDATE_CODE');
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