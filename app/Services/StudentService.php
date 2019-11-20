<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 19:58
 *
 * 客户相关数据service
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\ResponseError;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;

class StudentService
{
    //默认分页条数
    const DEFAULT_COUNT = 20;


    /**
     * 查看机构下学生
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectStudentByOrg($orgId, $page, $count, $params)
    {
        list($records, $total) = StudentModel::selectStudentByOrg($orgId, $page, $count, $params);
        foreach($records as &$r) {
            $r['student_level'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_LEVEL, $r['student_level']);
            $r['gender']        = DictService::getKeyValue(Constants::DICT_TYPE_GENDER, $r['gender']);
            //ai陪练到期日
            if($r['sub_status'] == StudentModel::SUB_STATUS_NORMAL) {
                if(empty($r['sub_end_date'])) {
                    $r['sub_end_date'] = StudentModel::NOT_ACTIVE_TEXT;
                }
            } else {
                $r['sub_end_date'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_SUB_STATUS, $r['sub_status']);
            }
            $r['is_first_pay'] = empty($r['first_pay_time']) ? '未付费' : '已付费';
            $r['flags'] = FlagsService::flagsToArray($r['flags']);
        }

        return [$records, $total];
    }

    /**
     * 更新学生详细信息
     * @param $studentId
     * @param $params
     * @return array|int
     */
    public static function updateStudentDetail($studentId, $params)
    {
        $student = StudentModel::getById($studentId);

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);

        $userCenter = new UserCenter($appId, $appSecret);
        $updResult = $userCenter->modifyStudent($student['uuid'], $params['name'], $params['birthday'], strval($params['gender']));
        if (!empty($updResult) && $updResult['code'] != 0){
            return $updResult;
        }

        $affectRow = StudentModel::updateStudent($studentId, $params);

        return $affectRow;
    }

    /**
     * 学生注册
     * @param $params
     * @param $operatorId
     * @return int|mixed|null|string
     */
    public static function studentRegister($params, $operatorId = 0)
    {
        //添加学生
        $res = self::insertStudent($params, $operatorId);
        if($res['code'] != Valid::CODE_SUCCESS){
            return $res;
        }

        $studentId = $res['data']['studentId'];

        return [
            'code'       => 0,
            'student_id' => $studentId,
        ];
    }

    /**
     * 用现有uuid注册
     * @param $uuid
     * @param $channelId
     * @param int $operatorId
     * @return array
     */
    public static function studentRegisterByUuid($uuid, $channelId, $operatorId = EmployeeModel::SYSTEM_EMPLOYEE_ID)
    {
        $student = StudentModel::getRecord([
            'uuid' => $uuid,
        ],[],false);

        if(!empty($student)) {
            return [
                'code'       => 0,
                'student_id' => $student['id'],
            ];
        }

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        $authResult = $userCenter->studentAuthorizationByUuid(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, $uuid, true);

        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $name = $authResult['name'] ?? Util::defaultStudentName($authResult['mobile']);
        $params['create_time'] = time();
        $studentId = StudentModel::saveStudent([
            'name' => $name,
            'mobile' => $authResult['mobile'],
            'gender' => $authResult['gender'],
            'birthday' => $authResult['birthday'],
            'channel_id' => $channelId,
        ], $authResult["uuid"], $operatorId);

        if(empty($studentId)) {
            return Valid::addErrors([], 'student', 'save_student_fail');
        }

        return [
            'code'       => 0,
            'student_id' => $studentId,
        ];
    }

    /**
     * 添加学生
     * 本地如果已经存在此学生，更新学生信息，返回学生id
     * 如果没有，新增学生记录，返回id
     * @param $params
     * @param int $operatorId
     * @return array
     */
    public static function insertStudent($params, $operatorId = 0)
    {
        $birthday   = $params['birthday'] ?? '';
        $gender     = $params['gender'] ?? StudentModel::GENDER_UNKNOWN;

        list($appId, $appSecret) = DictConstants::get(DictConstants::USER_CENTER, ['app_id_dss', 'app_secret_dss']);
        $userCenter = new UserCenter($appId, $appSecret);

        $authResult = $userCenter->studentAuthorization(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            $params['mobile'], $params['name'], '',$birthday, strval($gender));

        if (empty($authResult["uuid"])) {
            return Valid::addErrors([], "user_center", "uc_user_add_failed");
        }

        $uuid = $authResult['uuid'];

        $student = StudentModel::getRecord([
            'uuid' => $uuid,
        ],[],false);

        $time = time();
        if(empty($student)) {
            $params['create_time'] = $time;
            $studentId = StudentModel::saveStudent($params, $authResult["uuid"], $operatorId);
            if(empty($studentId)) {
                return Valid::addErrors([], 'student', 'save_student_fail');
            }
        } else {
            $studentId = $student['id'];
            $params['update_time'] = $time;
            $affectRows = StudentModel::updateRecord($studentId, $params, false);
            if($affectRows == 0) {
                return Valid::addErrors([], 'student', 'update_student_fail');
            }
        }

        return ['code' => Valid::CODE_SUCCESS, 'data' => ['studentId' => $studentId]];
    }

    /**
     * 查询一条指定机构和学生id的记录
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return array|null
     */
    public static function getOrgStudent($orgId, $studentId, $status = null)
    {
        return StudentModel::getOrgStudent($orgId, $studentId, $status);
    }

    /**
     * 绑定学生和机构，返回lastId
     * 关系存在时候，只更新，不插入新的记录
     * 已经绑定的不会返回错误，同样返回lastId
     * @param $orgId
     * @param $studentId
     * @return ResponseError|int|mixed|null|string
     */
    public static function bindOrg($orgId, $studentId)
    {
        $studentOrg = StudentOrgModel::getRecord(['org_id' => $orgId, 'student_id' => $studentId]);
        if(empty($studentOrg)) {
            $now = time();
            //save
            $lastId = StudentOrgModel::insertRecord([
                'org_id'      => $orgId,
                'student_id'  => $studentId,
                'status'      => StudentOrgModel::STATUS_NORMAL,
                'update_time' => $now,
                'create_time' => $now,
            ]);
            if($lastId == 0) {
                return new ResponseError('save_student_org_fail');
            }
            return $lastId;
        } else {
            //update
            if($studentOrg['status'] != StudentOrgModel::STATUS_NORMAL) {
                $affectRows = StudentOrgModel::updateStatus($orgId, $studentId, StudentOrgModel::STATUS_NORMAL);
                if($affectRows == 0) {
                    return new ResponseError('update_student_org_status_error');
                }
                return $studentOrg['id'];
            }
            return $studentOrg['id'];
        }
    }

    /**
     * 更新学生和机构关系的状态(解绑/绑定)
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateStatusWithOrg($orgId, $studentId, $status) {
        return StudentOrgModel::updateStatus($orgId, $studentId, $status);
    }

    public static function getStudentByIds($sIds)
    {
        return StudentOrgModel::getRecords(['student_id'=>$sIds,'status'=>StudentOrgModel::STATUS_NORMAL]);
    }

    /**
     * 批量给学生分配课管
     * @param $studentIds
     * @param $orgId
     * @param $ccId
     * @return int
     */
    public static function assignCC($studentIds, $orgId, $ccId)
    {
        return StudentOrgModel::assignCC($studentIds, $orgId, $ccId);
    }

    /**
     * 改变数组的格式
     * "students":[{"411":["30.00","280.00"]},{"410":["30.00","280.00"]}]
     * "students":{"411":["30.00","280.00"],"410":["30.00","280.00"]}
     * @param $students
     * @return array
     */
    public static function arrayPlus($students)
    {
        if (empty($students)) {
            return [];
        }

        $s = [];
        foreach ($students as $student) {
            $s += $student;
        }
        return $s;
    }

    /**
     * 更新用户的付费状态
     * @param $studentId
     * @return int|null
     */
    public static function updateUserPaidStatus($studentId)
    {
        //当前用户是否已经付费
        $studentInfo = StudentOrgModel::getRecord(['student_id' => $studentId], ['id','is_first_pay']);
        if ($studentInfo['is_first_pay'] != StudentOrgModel::HAS_PAID) {
            return StudentOrgModel::updateRecord($studentInfo['id'], ['is_first_pay' => StudentOrgModel::HAS_PAID, 'first_pay_time' => time()]);
        }
        return null;
    }

    public static function getByUuid($uuid)
    {
        return StudentModel::getRecord(['uuid' => $uuid], '*', false);
    }

    /**
     * 扣除学生服务时长
     * @param $studentId
     * @param $num
     * @param $unit
     * @return int
     */
    public static function reduceSubDuration($studentId, $num, $unit)
    {
        $student = StudentModel::getById($studentId);

        if (empty($student['sub_end_date'])) {
            return 0;
        }

        $subEndDate = $student['sub_end_date'];
        $subEndTime = strtotime($subEndDate);

        $timeStr = '-' . $num . ' ';
        switch ($unit) {
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

        return StudentModel::updateRecord($studentId, $studentUpdate, false);
    }

    /**
     * 和自己关联的激活码
     * 包括购买的和使用的
     * @param $studentId
     * @return array
     */
    public static function selfGiftCode($studentId)
    {
        $codes = GiftCodeModel::getRecords([
            'OR' => [
                'AND #1' => ['buyer[!]' => $studentId, 'apply_user' => $studentId],
                'AND #2' => ['buyer' => $studentId, 'generate_channel' => [
                    GiftCodeModel::BUYER_TYPE_STUDENT,
                    GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE,
                    GiftCodeModel::BUYER_TYPE_ERP_ORDER
                ]]
            ]
        ], '*', false);

        $result = [];
        foreach ($codes as $code) {
            switch ($code['code_status']) {
                case GiftCodeModel::CODE_STATUS_INVALID:
                    $statusStr = '已作废'; break;
                case GiftCodeModel::CODE_STATUS_NOT_REDEEMED:
                    $statusStr = '未使用'; break;
                case GiftCodeModel::CODE_STATUS_HAS_REDEEMED:
                    $statusStr = ($code['apply_user'] == $studentId ? '已使用' : '被他人使用'); break;
                default:
                    $statusStr = '';
            }

            $info = [
                'code' => $code['code'],
                'status' => $code['code_status'],
                'status_zh' => $statusStr,
                'duration' => $code['valid_num'] . Dict::getCodeTimeUnit($code['valid_units']),
            ];

            $result[] = $info;
        }

        return $result;
    }

    /**
     * 给用户添加时长
     * @param $studentID
     * @param $num
     * @param $units
     * @return array
     * @throws RunTimeException
     */
    public static function addSubDuration($studentID, $num, $units)
    {
        $student = StudentModel::getById($studentID);
        if (empty($student)) {
            $e = new RunTimeException(['student_not_found']);
            $e->sendCaptureMessage(['$studentID' => $studentID]);
            throw $e;
        }

        $today = date('Ymd');
        if (empty($student['sub_end_date']) || $student['sub_end_date'] < $today) {
            $subEndDate = $today;
        } else {
            $subEndDate = $student['sub_end_date'];
        }
        $subEndTime = strtotime($subEndDate);

        $unitsStr = GiftCodeModel::CODE_TIME_UNITS[$units];
        if (empty($unitsStr)) {
            $e = new RunTimeException(['invalid_gift_code_units']);
            $e->sendCaptureMessage(['$units' => $units]);
            throw $e;
        }

        $timeStr = '+' . $num . ' ' . $unitsStr;
        $newSubEndDate = date('Ymd', strtotime($timeStr, $subEndTime));

        $studentUpdate = [
            'sub_end_date' => $newSubEndDate,
            'update_time'  => time(),
        ];
        if (empty($student['sub_start_date'])) {
            $studentUpdate['sub_start_date'] = $today;
        }

        $affectRows = StudentModel::updateRecord($studentID, $studentUpdate);
        if($affectRows == 0) {
            $e = new RunTimeException(['update_student_fail']);
            $e->sendCaptureMessage(['$studentID' => $studentID, '$studentUpdate' => $studentUpdate]);
            throw $e;
        }

        return [
            'sub_start_date' => $student['sub_start_date'] ?: $studentUpdate['sub_start_date'],
            'sub_end_date' => $studentUpdate['sub_end_date'],
        ];
    }

    public static function updateReviewCourseFlag($studentID, $hasReviewCourse)
    {
        $affectRows = StudentModel::updateRecord($studentID, [
            'has_review_course' => $hasReviewCourse,
        ], false);

        if($affectRows == 0) {
            return 'update_student_fail';
        }

        return null;
    }
}