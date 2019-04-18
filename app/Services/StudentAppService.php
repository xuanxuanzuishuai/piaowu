<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\APIValid;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use APP\Models\AppConfigModel;
use App\Models\StudentAppModel;
use App\Models\GiftCodeModel;
use GuzzleHttp\Client;

class StudentAppService
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;

    /**
     * 手机验证码登录
     *
     * @param string $mobile 手机号
     * @param int $code 短信验证码
     * @return array [0]errorCode [1]登录数据
     */
    public static function login($mobile, $code)
    {
        // 检查验证码
        if (!self::checkValidateCode($mobile, $code)) {
            return ['validate_code_error'];
        }

        $student = StudentAppModel::getStudentInfo(null, $mobile);

        // 新用户自动注册
        if (empty($student)) {
            $newStudent = self::studentRegister($mobile);

            if (empty($newStudent)) {
                return ['student_register_fail'];
            }

            $student = StudentAppModel::getStudentInfo(null, $mobile);
        }

        if (empty($student)) {
            return ['unknown_student'];
        }

        $token = StudentAppModel::genStudentToken($student['id']);
        StudentAppModel::setStudentToken($student['id'], $token);

        $loginData = [
            'id' => $student['id'],
            'uuid' => $student['uuid'],
            'username' => $student['name'],
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


    /**
     * token登录
     *
     * @param string $mobile 手机号
     * @param string $token 登录返回的token
     * @return array [0]errorCode [1]登录数据
     */
    public static function loginWithToken($mobile, $token)
    {
        $student = StudentAppModel::getStudentInfo(null, $mobile);
        $cacheToken = StudentAppModel::getStudentToken($student['id']);

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

    /**
     * 注册新用户
     *
     * @param $mobile
     * @return null|array 失败返回null 成功返回['student_id' => x, 'uuid' => x, 'is_new' => x]
     */
    public static function studentRegister($mobile)
    {
        $params = [
            'mobile' => $mobile,
            'source_id' => 1,
            'app_id' => 1,
        ];

        $client = new Client();

        $url = AppConfigModel::get(AppConfigModel::ERP_URL_KEY);
        $api = AppConfigModel::get(AppConfigModel::ERP_API_STUDENT_REGISTER_KEY);
        $response = $client->request('POST', $url . $api, [
            'form_params' => $params,
            'debug' => false
        ]);

        $body = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        if (200 != $statusCode) {
            SimpleLogger::error(__FILE__ . __LINE__, [
                'msg' => 'student reg error, network error.',
                'statusCode' => $statusCode
            ]);
            return null;
        }

        $data = json_decode($body, true);
        if (empty($data)) {
            SimpleLogger::error(__FILE__ . __LINE__, [
                'msg' => 'student reg error, response body decode error.',
                'body' => $body,
                'data' => $data
            ]);
            return null;
        }

        if ($data['code'] != APIValid::CODE_SUCCESS) {
            SimpleLogger::info(__FILE__ . __LINE__, [
                'msg' => 'student reg failure.',
                'data' => $data
            ]);
            return null;
        }

        $student = self::addStudent($mobile);

        if (empty($student)) {
            SimpleLogger::info(__FILE__ . __LINE__, [
                'msg' => 'student reg error, add new student error.',
            ]);
            return null;
        }

        return $student;
    }

    /**
     * 添加app新用户
     *
     * @param string $mobile 手机号
     * @return array|null 用户数据
     */
    public static function addStudent($mobile)
    {
        $student = StudentService::getStudentByMobile($mobile);
        $user = [
            'student_id' => $student['id'],
            'uuid' => $student['uuid'],
            'mobile' => $mobile,
            'create_time' => time(),
            'sub_status' => StudentAppModel::SUB_STATUS_ON,
            'sub_start_date' => 0,
            'sub_end_date' => 0,
        ];

        $id = StudentAppModel::insertRecord($user);
        return empty($id) ? null : $user;
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

    /**
     * 使用激活码
     * @param string $code 激活码
     * @param int $studentID 用户
     * @return array [0]errorCode [1]成功返回到期时间，失败返回null
     */
    public static function redeemGiftCode($code, $studentID)
    {
        // 验证code
        $gift = GiftCodeModel::getByCode($code);
        if (empty($gift)) {
            return ['gift_code_error'];
        }

        switch ($gift['code_status']) {
            case GiftCodeModel::CODE_STATUS_HAS_REDEEMED:
                return ['gift_code_has_been_redeemed'];
            case GiftCodeModel::CODE_STATUS_INVALID:
                return ['gift_code_is_invalid'];
        }

        // 添加时间

        $student = StudentAppModel::getStudentInfo($studentID, null);
        if (empty($student)) {
            return ['unknown_student'];
        }

        $today = date('Ymd');
        if (empty($student['sub_end_date']) || $student['sub_end_date'] < $today) {
            $subEndDate = $today;
        } else {
            $subEndDate = $student['sub_end_date'];
        }
        $subEndTime = strtotime($subEndDate);

        $timeStr = '+' . $gift['valid_num'] . ' ';
        switch ($gift['valid_units']) {
            case GiftCodeModel::CODE_TIME_DAY:
                $timeStr .= 'day';
                break;
            case GiftCodeModel::CODE_TIME_MONTH:
                $timeStr .= 'month';
                break;
            case GiftCodeModel::CODE_TIME_YEAR:
                $timeStr .= 'year';
                break;
            default:
                $timeStr .= 'day';
        }
        $newSubEndDate = date('Ymd', strtotime($timeStr, $subEndTime));

        $studentUpdate = ['sub_end_date' => $newSubEndDate];
        if (empty($student['sub_start_date'])) {
            $studentUpdate['sub_start_date'] = $today;
        }
        StudentAppModel::updateRecord($student['id'], $studentUpdate);

        GiftCodeModel::updateRecord($gift['id'], [
            'apply_student' => $student['student_id'],
            'be_active_time' => time(),
            'code_status' => GiftCodeModel::CODE_STATUS_HAS_REDEEMED,
        ]);

        $result = [
            'new_sub_end_date' => $newSubEndDate,
            'generate_channel' => $gift['generate_channel'],
            'buyer' => $gift['buyer'] ?? 0,
            'buy_time' => $gift['buy_time'] ?? 0,
        ];

        return [null, $result];
    }

    /**
     * 获取用户服务订阅状态
     * @param $studentID
     * @return bool
     */
    public static function getSubStatus($studentID)
    {
        $student = StudentAppModel::getStudentInfo($studentID, null);
        if ($student['sub_status'] != StudentAppModel::SUB_STATUS_ON) {
            return false;
        }

        $endTime = strtotime($student['sub_end_date']) + 86400;
        return $endTime > time();
    }
}