<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/9
 * Time: 6:14 PM
 */

namespace App\Services;


use App\Libs\DictConstants;
use App\Libs\ResponseError;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\GiftCodeModel;

class StudentServiceForApp
{
    const VALIDATE_CODE_CACHE_KEY_PRI = 'v_code_';
    const VALIDATE_CODE_TIME_CACHE_KEY_PRI = 'v_code_time_';
    const VALIDATE_CODE_EX = 300;
    const VALIDATE_CODE_WAIT_TIME = 60;

    // 体验时长(天)
    const TRIAL_DAYS = 7;

    // 用户操作类型 观看付费服务介绍
    const ACTION_READ_SUB_INFO = 'act_sub_info';

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
        if (!CommonServiceForApp::checkValidateCode($mobile, $code)) {
            return ['validate_code_error'];
        }

        $student = StudentModelForApp::getStudentInfo(null, $mobile);

        // 新用户自动注册
        if (empty($student)) {
            $newStudent = self::studentRegister($mobile, StudentModel::CHANNEL_APP_REGISTER);

            if (empty($newStudent)) {
                return ['student_register_fail'];
            }

            $student = StudentModelForApp::getStudentInfo(null, $mobile);
        }

        if (empty($student)) {
            return ['unknown_student'];
        }

        $token = StudentModelForApp::genStudentToken($student['id']);
        StudentModelForApp::setStudentToken($student['id'], $token);

        $teachers = StudentModelForApp::getTeacherIds($student['id']);

        $loginData = [
            'id' => $student['id'],
            'uuid' => $student['uuid'],
            'student_name' => $student['name'],
            'avatar' => Util::getQiNiuFullImgUrl($student['thumb']),
            'mobile' => $student['mobile'],
            'sub_status' => $student['sub_status'],
            'sub_start_date' => $student['sub_start_date'],
            'sub_end_date' => $student['sub_end_date'],
            'trial_start_date' => $student['trial_start_date'],
            'trial_end_date' => $student['trial_end_date'],
            'act_sub_info' => (int)$student['act_sub_info'],
            'token' => $token,
            'teachers' => $teachers
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
        $student = StudentModelForApp::getStudentInfo(null, $mobile);
        $cacheToken = StudentModelForApp::getStudentToken($student['id']);

        if (empty($cacheToken) || $cacheToken != $token) {
            return ['invalid_token'];
        }

        $teachers = StudentModelForApp::getTeacherIds($student['id']);

        $loginData = [
            'id' => $student['id'],
            'uuid' => $student['uuid'],
            'student_name' => $student['name'],
            'avatar' => Util::getQiNiuFullImgUrl($student['thumb']),
            'mobile' => $student['mobile'],
            'sub_status' => $student['sub_status'],
            'sub_start_date' => $student['sub_start_date'],
            'sub_end_date' => $student['sub_end_date'],
            'trial_start_date' => $student['trial_start_date'],
            'trial_end_date' => $student['trial_end_date'],
            'act_sub_info' => (int)$student['act_sub_info'],
            'token' => $token,
            'teachers' => $teachers
        ];

        return [null, $loginData];
    }

    public static function registerStudentInUserCenter($name, $mobile, $uuid = '', $birthday = '', $gender = '')
    {
        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_student', 'app_secret_student']);
        $userCenter = new UserCenter($appId, $appSecret);
        $authResult = $userCenter->studentAuthorization(8, $mobile, $name, $uuid, $birthday, $gender);
        return $authResult;
    }

    /**
     * 注册新用户
     *
     * @param $mobile
     * @param $channel
     * @param $name
     * @return null|array 失败返回null 成功返回['student_id' => x, 'uuid' => x, 'is_new' => x]
     */
    public static function studentRegister($mobile, $channel, $name=null)
    {
        if (empty($name)) {
            $name = Util::defaultStudentName($mobile);
        }
        $result = self::registerStudentInUserCenter($name, $mobile);
        if (empty($result['uuid'])) {
            SimpleLogger::info(__FILE__ . __LINE__, $result);
            return null;
        }

        $uuid = $result['uuid'];
        $lastId = self::addStudent($mobile, $name, $uuid, $channel);

        if (empty($lastId)) {
            SimpleLogger::info(__FILE__ . __LINE__, [
                'msg' => 'user reg error, add new user error.',
            ]);
            return null;
        }

        return $lastId;
    }

    /**
     * 添加app新用户
     *
     * @param string $mobile 手机号
     * @param string $name 昵称
     * @param string $uuid
     * @param int $channel
     * @return array|null 用户数据
     */
    public static function addStudent($mobile, $name, $uuid, $channel)
    {
        $user = [
            'uuid' => $uuid,
            'mobile' => $mobile,
            'name' => $name,
            'create_time' => time(),
            'sub_status' => StudentModelForApp::SUB_STATUS_ON,
            'sub_start_date' => 0,
            'sub_end_date' => 0,
            'channel_id' => $channel
        ];

        $id = StudentModelForApp::insertRecord($user, false);

        return $id == 0 ? null : $id;
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

        if (in_array($gift['generate_channel'], [
            GiftCodeModel::BUYER_TYPE_STUDENT,
            GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE,
            GiftCodeModel::BUYER_TYPE_ERP_ORDER
        ]) && $gift['buyer'] != $studentID) {
            return ['gift_code_user_invalid'];
        }

        // 添加时间

        $student = StudentModelForApp::getStudentInfo($studentID, null);
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

        $studentUpdate = [
            'sub_end_date' => $newSubEndDate,
            'update_time'  => time(),
        ];
        if (empty($student['sub_start_date'])) {
            $studentUpdate['sub_start_date'] = $today;
        }

        $studentId = $student['id'];

        $affectRows = StudentModelForApp::updateRecord($studentId, $studentUpdate);
        if($affectRows == 0) {
            return ['update_student_fail'];
        }

        $affectRows = GiftCodeModel::updateRecord($gift['id'], [
            'apply_user'     => $studentId,
            'be_active_time' => time(),
            'code_status'    => GiftCodeModel::CODE_STATUS_HAS_REDEEMED,
        ]);
        if($affectRows == 0) {
            return ['update_gift_code_fail'];
        }

        // 机构激活码使用时自动绑定用户
        if ($gift['generate_channel'] == GiftCodeModel::BUYER_TYPE_ORG) {
            $errOrLastId = StudentService::bindOrg($gift['buyer'], $studentId);
            if($errOrLastId instanceof ResponseError) {
                return [$errOrLastId->getErrorMsg()];
            }
        }

        $result = [
            'new_sub_end_date' => $newSubEndDate,
            'generate_channel' => $gift['generate_channel'],
            'buyer'            => $gift['buyer'] ?? 0,
            'buy_time'         => $gift['buy_time'] ?? 0,
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
        $student = StudentModelForApp::getStudentInfo($studentID, null);
        if ($student['sub_status'] != StudentModelForApp::SUB_STATUS_ON) {
            return false;
        }

        $endTime = strtotime($student['sub_end_date']) + 86400;
        return $endTime > time();
    }

    public static function trial($studentID)
    {
        $student = StudentModelForApp::getStudentInfo($studentID, null);
        if (empty($student)) {
            return ['unknown_student'];
        }

        // 体验过的用户无法领取体验资格
        if ($student['trial_end_date'] > 0) {
            return ['cant_trial'];
        }

        $today = date('Ymd');

        // 在服务期内无法领取体验资格
        if ($student['sub_end_date'] > $today) {
            return ['cant_trial'];
        }

        $endDate = date('Ymd', strtotime('+' . self::TRIAL_DAYS . ' day'));

        $affectRows = StudentModelForApp::updateRecord($studentID, [
            'trial_start_date' => $today,
            'trial_end_date' => $endDate,
            'sub_start_date' => $today,
            'sub_end_date' => $endDate,
            'update_time'  => time(),
        ]);
        if($affectRows == 0) {
            return ['update_student_fail'];
        }

        $result = [
            'trial_start_date' => $today,
            'trial_end_date' => $endDate,
            'sub_start_date' => $today,
            'sub_end_date' => $endDate,
        ];

        return [null, $result];

    }

    public static function action($studentID, $type) {

        $affectRows = StudentModelForApp::updateRecord($studentID, [
            $type . '[+]' => 1,
        ]);

        if($affectRows == 0) {
            return ['update_student_fail'];
        }

        $student = StudentModelForApp::getById($studentID);

        return [null, (int)$student[$type]];
    }

    public static function setNickname($studentID, $nickname) {
        $affectRows = StudentModelForApp::updateRecord($studentID, [
            'name' => $nickname,
        ]);

        if($affectRows == 0) {
            return 'update_student_fail';
        }

        return null;
    }
}