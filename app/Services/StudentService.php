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
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\CollectionModel;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\StudentAssistantLogModel;
use App\Models\StudentCollectionLogModel;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;
use App\Models\UserWeixinModel;

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
            //如果激活码使用时间超过10年，在激活码后面的有效期显示为【长期有效】，不显示具体的年限
            if ($code['valid_units'] == GiftCodeModel::CODE_TIME_YEAR) {
                $validityTime = $code['valid_num'] * 12;
            } elseif ($code['valid_units'] == GiftCodeModel::CODE_TIME_DAY) {
                $validityTime = round($code['valid_num'] / 31);
            } else {
                $validityTime = $code['valid_num'];
            }
            $duration = $code['valid_num'] . Dict::getCodeTimeUnit($code['valid_units']);
            //判断条件为月的数量：10年120个月
            if ($validityTime > 120) {
                $duration = "长期有效";
            }

            $info = [
                'code' => $code['code'],
                'status' => $code['code_status'],
                'status_zh' => $statusStr,
                'duration' => $duration,
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

    /**
     * 获取学生详情数据
     * @param $studentId
     * @return array
     */
    public static function getStudentDetail($studentId)
    {
        $student = StudentModel::getStudentDetail($studentId);
        if(empty($student)){
            return [];
        }
        //获取学生微信信息
        $studentWeChatInfo = self::getStudentWeChat($studentId);
        //获取学生转介绍信息
        $refereeInfo = self::getStudentReferee($student['uuid'], "referrer_uuid");
        //获取学生注册渠道
        $channel = self::getStudentChannel($student['channel_id']);
        return self::formatStudentInfo($student, $studentWeChatInfo, $refereeInfo, $channel);
    }

    /**
     * 获取学生渠道数据
     * @param $channelId
     * @return array
     */
    public static function getStudentChannel($channelId)
    {
        if(empty($channelId)){
            return [];
        }
        $channel = ChannelService::getChannelById($channelId);
        $data['channel'] = $channel['name'];
        $data['channel_id'] = $channelId;
        if(!empty($channel['parent_id'])){
            $parentChannel = ChannelService::getChannelById($channel['parent_id']);
            $data['parent_channel'] = $parentChannel['name'];
            $data['parent_channel_id'] = $parentChannel['id'];
        }
        return $data;
    }

    /**
     * 根式化学生详情页数据
     * @param $student
     * @param $studentWeChatInfo
     * @param $refereeInfo
     * @param $channel
     * @return array
     */
    public static function formatStudentInfo($student, $studentWeChatInfo, $refereeInfo, $channel)
    {
        $data = [];
        $data['student_id'] = $student['id'];
        $data['mobile'] = $student['mobile'];
        $data['student_name'] = $student['name'];
        $data['collection_id'] = $student['collection_id'];
        $data['collection_name'] = $student['collection_name'];
        $data['pay_status'] = empty($student['first_pay_time']) ? '未付费' : '已付费';
        //计算过期时间戳
        $expireTime = strtotime($student['sub_end_date'].' 00:00');
        $data['expire_time'] = empty($expireTime) ? '-' : date('Y-m-d', $expireTime);
        $data['effect_status'] = ($expireTime > time() && $student['sub_status']) ?  '未过期' : '已过期';
        $data['wechat_bind'] = empty($studentWeChatInfo) ? '未绑定' : '已绑定';
        $data['wechat_name'] = '-';
        //获取学生阶段MAP
        $stepMap = DictService::getTypeMap(Constants::DICT_TYPE_REVIEW_COURSE_STATUS);
        $data['student_step'] = isset($stepMap[$student['has_review_course']]) ? $stepMap[$student['has_review_course']] : '-';
        $data['referee'] = empty($refereeInfo) ? '-' : $refereeInfo['name'] . "(" . $refereeInfo['mobile'] . ")";
        $data['register_time'] = date('Y-m-d H:i', $student['create_time']);
        $data['assistant_id'] = $student['assistant_id'];
        $data['assistant_name'] = $student['assistant_name'];
        $data['is_add_assistant_wx'] = $student['is_add_assistant_wx'];
        $data['channel'] = $channel;
        $data['wechat_account'] = $student['wechat_account'];
        return $data;
    }

    /**
     * 获取学生转介绍数据
     * @param $studentUuid
     * @param $field
     * @return array
     */
    public static function getStudentReferee($studentUuid, $field)
    {
        $params = [
            'uuid' => $studentUuid,
            'field' => $field,
        ];
        $data = ErpReferralService::getUserReferralInfo($params);
        //获取转介绍人信息
        $referralInfo = StudentModel::getRecord(['uuid' => $data[0]["referrer_uuid"]], ['name', 'mobile'], false);
        return $referralInfo;
    }

    /**
     * 获取学生微信数据
     * @param $studentId
     * @return mixed
     */
    public static function getStudentWeChat($studentId)
    {
        return UserWeixinModel::getBoundInfoByUserId($studentId,
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
    }

    /**
     * 编辑学生添加助教微信状态
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateAddAssistantStatus($studentId, $status)
    {
        $status = empty($status) ? StudentModel::UN_ADD_STATUS : StudentModel::ADD_STATUS;
        $data = ['is_add_assistant_wx' => $status];
        return StudentModel::updateStudent($studentId, $data);
    }

    /**
     * @param $params
     * @param $page
     * @param $count
     * @param $employeeId
     * @return array
     */
    public static function searchList($params, $page, $count, $employeeId)
    {
        list($count, $list) = StudentModel::studentList($params, $page, $count, $employeeId);
        if($count > 0){
            $list = self::formatListData($list);
        }
        return ['total_count' => $count, 'list' => $list];
    }

    /**
     * 格式化列表数据
     * @param $list
     * @return array
     */
    public static function formatListData($list)
    {
        $data = [];
        $time = time();
        // 获取学生阶段MAP
        $stepMap = DictService::getTypeMap(Constants::DICT_TYPE_REVIEW_COURSE_STATUS);
        // 获取添加助教微信状态map
        $addAssistantMap = DictService::getTypeMap(Constants::DICT_TYPE_ADD_ASSISTANT_WX_STATUS);

        // 格式化学生数据
        foreach($list as $item){
            $row = [];
            $row['student_id'] = $item['student_id'];
            $row['name'] = $item['name'];
            $row['mobile'] = Util::hideUserMobile($item['mobile']);
            $row['pay_status'] = empty($item['first_pay_time']) ? '未付费' : '已付费';
            //计算过期时间戳
            $expireTime = strtotime($item['sub_end_date'].' 00:00');
            $row['effect_status'] = ($expireTime > $time && $item['sub_status']) ?  '未过期' : '已过期';
            $row['expire_time'] = empty($expireTime) ? '-' : date('Y-m-d', $expireTime);
            $row['student_step'] = isset($stepMap[$item['has_review_course']]) ? $stepMap[$item['has_review_course']] : '-';
            $row['wechat_bind'] = empty($item['wx_id']) ? '未绑定' : '已绑定';
            $row['assistant_name'] = $item['assistant_name'];
            $row['is_add_assistant_wx'] = isset($addAssistantMap[$item['is_add_assistant_wx']]) ? $addAssistantMap[$item['is_add_assistant_wx']] : '-';
            $row['collection_name'] = $item['collection_name'];
            $row['channel'] = $item['channel_name'];
            $row['parent_channel'] = $item['parent_channel_name'];
            $row['register_time'] = date('Y-m-d H:i', $item['create_time']);
            $remark['latest_remark_status'] = DictService::getKeyValue(Constants::DICT_TYPE_STUDENT_REMARK_STATUS, $item['latest_remark_status']);
            $row['allot_collection_time'] = empty($item['allot_collection_time']) ? '-' : date('Y-m-d H:i', $item['allot_collection_time']);
            $row['wechat_account'] = $item['wechat_account'];
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 学生分配集合
     * @param $studentIds
     * @param $collectionId
     * @param $employeeId
     * @return array
     */
    public static function allotCollection($studentIds, $collectionId, $employeeId)
    {
        $students = StudentModel::getStudentByIds($studentIds);
        $studentCount = count($studentIds);
        if(empty($students) || count($students) != $studentCount){
            return Valid::addErrors([], 'student_ids_error', 'student_ids_error');
        }
        $collection = CollectionModel::getById($collectionId);
        if(empty($collection)){
            return Valid::addErrors([], 'collection_id_error', 'collection_id_error');
        }
        $time = time();
        //只分配未结班的班级, 已经结班则返回错误
        if($collection['teaching_end_time'] <= $time){
            return Valid::addErrors([], 'collection_is_over', 'collection_is_over');
        }
        //确定班级容量是否超出
        $collectionStudentCount = self::getCollectionStudentCount($collectionId);
        if(($collectionStudentCount + $studentCount) > $collection['capacity']){
            return Valid::addErrors([], 'collection_capacity_is_over', 'collection_capacity_is_over');
        }
        //添加班级分配日志
        $logData = self::formatAllotCollectionLogData($students, $collectionId, $employeeId, $time);
        $res = self::addAllotCollectionLog($logData);
        if(!$res){
            return Valid::addErrors([], 'add_allot_collection_log_failed', 'add_allot_collection_log_failed');
        }
        //添加助教分配日志
        $logData = self::formatAllotAssistantLogData($students, $collection['assistant_id'], $employeeId, $time);
        $res = self::addAllotAssistantLog($logData);
        if(!$res){
            return Valid::addErrors([], 'add_allot_assistant_log_failed', 'add_allot_assistant_log_failed');
        }
        $cnt = self::updateStudentCollection($studentIds, $collectionId, $collection['assistant_id'], $time);
        if($cnt != $studentCount){
            return Valid::addErrors([], 'update_student_data_failed', 'update_student_data_failed');
        }
        return Valid::formatSuccess();
    }

    /**
     * 获取班级已分配学生数
     * @param $collectionId
     * @return number
     */
    public static function getCollectionStudentCount($collectionId)
    {
        return StudentModel::getCollectionStudentCount($collectionId);
    }

    /**
     * 格式化日志数据
     * @param $students
     * @param $collectionId
     * @param $employeeId
     * @param $time
     * @return array
     */
    public static function formatAllotCollectionLogData($students, $collectionId, $employeeId, $time)
    {
        $data = [];
        foreach($students as $student){
            $row = [];
            $row['student_id'] = $student['id'];
            $row['old_collection_id'] = $student['collection_id'] ?? 0;
            $row['new_collection_id'] = $collectionId;
            $row['create_time'] = $time;
            $row['operator_id'] = $employeeId;
            $row['operate_type'] = StudentCollectionLogModel::OPERATE_TYPE_ALLOT;
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 添加分配集合日志
     * @param $data
     * @return bool
     */
    public static function addAllotCollectionLog($data)
    {
        return StudentCollectionLogModel::batchInsert($data);
    }

    /**
     * 更新学生集合数据
     * @param $studentIds
     * @param $collectionId
     * @param $assistantId
     * @param $time
     * @return int|null
     */
    public static function updateStudentCollection($studentIds, $collectionId, $assistantId, $time)
    {
        return StudentModel::updateStudentCollection($studentIds, $collectionId, $assistantId, $time);
    }

    /**
     * 学生分配助教
     * @param $studentIds
     * @param $assistantId
     * @param $employeeId
     * @return array
     */
    public static function allotAssistant($studentIds, $assistantId, $employeeId)
    {
        $students = StudentModel::getStudentByIds($studentIds);
        $studentCount = count($studentIds);
        if(empty($students) || count($students) != $studentCount){
            return Valid::addErrors([], 'student_ids_error', 'student_ids_error');
        }
        $time = time();
        $logData = self::formatAllotAssistantLogData($students, $assistantId, $employeeId, $time);
        $res = self::addAllotAssistantLog($logData);
        if(!$res){
            return Valid::addErrors([], 'add_allot_assistant_log_failed', 'add_allot_assistant_log_failed');
        }
        $cnt = self::updateStudentAssistant($studentIds, $assistantId, $time);
        if($cnt != $studentCount){
            return Valid::addErrors([], 'update_student_data_failed', 'update_student_data_failed');
        }
        return Valid::formatSuccess();
    }

    /**
     * 格式化日志数据
     * @param $students
     * @param $assistantId
     * @param $employeeId
     * @param $time
     * @param $operateType
     * @param $extraInfo
     * @return array
     */
    public static function formatAllotAssistantLogData($students, $assistantId, $employeeId, $time, $operateType = StudentAssistantLogModel::OPERATE_TYPE_ALLOT, $extraInfo = '')
    {
        $data = [];
        foreach($students as $student){
            $row = [];
            $row['student_id'] = $student['id'];
            $row['old_assistant_id'] = $student['assistant_id'] ?? 0;
            $row['new_assistant_id'] = $assistantId;
            $row['create_time'] = $time;
            $row['operator_id'] = $employeeId;
            $row['operate_type'] = $operateType;
            $row['extra_info'] = $extraInfo;
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 更新学生分配助教日志
     * @param $data
     * @return bool
     */
    public static function addAllotAssistantLog($data)
    {
        return StudentAssistantLogModel::batchInsert($data);
    }

    /**
     * 更新学生助教信息
     * @param $studentIds
     * @param $assistantId
     * @param $time
     * @return int|null
     */
    public static function updateStudentAssistant($studentIds, $assistantId, $time)
    {
        return StudentModel::updateStudentAssistant($studentIds, $assistantId, $time);
    }

    public static function getStudentByMobile($mobile)
    {
        return StudentModel::getRecord(['mobile' => $mobile]);
    }

    public static function updateStudentRemark($studentId, $remarkId, $remarkStatus)
    {
        StudentModel::updateStudent($studentId, ['last_remark_id' => $remarkId, 'latest_remark_status' => $remarkStatus]);
    }

    /**
     * 编辑学生微信账号数据
     * @param $studentId
     * @param $wechatAccount
     * @return int|null
     */
    public static function updateAddWeChatAccount($studentId, $wechatAccount)
    {
        $data = ['wechat_account' => $wechatAccount];
        return StudentModel::updateStudent($studentId, $data);
    }

    /**
     * 通过uuid获取学生信息
     * @param $uuidList
     * @param $field
     * @return array
     */
    public static function getByUuids($uuidList, $field = [])
    {
        return StudentModel::getRecords(['uuid' => $uuidList], $field, false);
    }
}