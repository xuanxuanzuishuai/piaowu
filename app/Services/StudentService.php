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

use App\Libs\AliContentCheck;
use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\ResponseError;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\CollectionModel;
use App\Models\DeptPrivilegeModel;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\PackageExtModel;
use App\Models\ReviewCourseModel;
use App\Models\SharePosterModel;
use App\Models\StudentAssistantLogModel;
use App\Models\StudentCollectionLogModel;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;
use App\Models\UserWeixinModel;
use App\Models\StudentCourseManageLogModel;
use App\Models\StudentAcquiredLogModel;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\QueueService;

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
        $data['mobile'] = Util::hideUserMobile($student['mobile']);
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
        $data['referee'] = empty($refereeInfo) ? '-' : $refereeInfo['name'] . "(" . Util::hideUserMobile($refereeInfo['mobile']) . ")";
        $data['referee_id'] = $refereeInfo['id'];
        $data['register_time'] = date('Y-m-d H:i', $student['create_time']);
        $data['assistant_id'] = $student['assistant_id'];
        $data['assistant_name'] = $student['assistant_name'];
        $data['is_add_assistant_wx'] = $student['is_add_assistant_wx'];
        $data['channel'] = $channel;
        $data['wechat_account'] = $student['wechat_account'];
        $data['sync_status'] = $student['sync_status'];
        $data['course_manage_name'] = $student['course_manage_name'];
        $data['thumb'] = $student['thumb'] ? AliOSS::replaceCdnDomainForDss($student["thumb"]) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
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
        $referralInfo = StudentModel::getRecord(['uuid' => $data[0]["referrer_uuid"]], ['name', 'mobile', 'id'], false);
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
     * 编辑学生添加课管微信状态
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateAddCourseStatus($studentId, $status)
    {
        $status = empty($status) ? Constants::STATUS_FALSE : Constants::STATUS_TRUE;
        $data = ['is_add_course_wx' => $status];
        return StudentModel::updateStudent($studentId, $data);
    }

    /**获取学员管理
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function searchList($params, $page, $count)
    {
        list($count, $list) = StudentModel::studentList($params, $page, $count);
        if($count > 0){
            $list = self::formatListData($list);
        }
        return ['total_count' => $count, 'list' => $list];
    }

    /**
     * 获取课管学员管理
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function CourseStudentList($params, $page, $count)
    {
        list($count, $list) = StudentModel::CourseStudentList($params, $page, $count);
        if($count > 0){
            $list = self::formatCourseListData($list);
        }
        return ['total_count' => $count, 'list' => $list];
    }


    public static function getLeaderPrivilegeMemberId($employeeDeptId, $employeeId, $targetDeptId = null, $targetId = null)
    {
        // 查询自己的数据
        if ($targetId == $employeeId) {
            return [$employeeId];
        }

        // 查询下属员工数据
        if (!empty($targetId)) {
            $targetEmployee = EmployeeModel::getById($targetId);
            $targetDeptId = $targetEmployee['dept_id'];

            $isMember = DeptService::isSubDept($targetDeptId, $employeeDeptId, DeptPrivilegeModel::DATA_TYPE_STUDENT);

            if ($isMember) {
                return [$targetId];
            } else {
                return [];
            }
        }

        // 查询下属组
        if (empty($targetDeptId)) {
            $targetDeptId = $employeeDeptId;
        }

        $isMember = DeptService::isSubDept($targetDeptId, $employeeDeptId, DeptPrivilegeModel::DATA_TYPE_STUDENT);

        if ($isMember) {
            $members = DeptService::getMembers($targetDeptId, DeptPrivilegeModel::DATA_TYPE_STUDENT);
            return array_column($members, 'id');
        } else {
            return [];
        }
    }

    public static function getDeptPrivilege($deptId)
    {
        $members = DeptService::getMembers($deptId, DeptPrivilegeModel::DATA_TYPE_STUDENT);

        list($assistantRoleId, $courseManageRoleId) = DictConstants::getValues(DictConstants::ORG_WEB_CONFIG,
            ['assistant_role', 'course_manage_role']);

        $privilegeParams = [];
        foreach ($members as $m) {
            if ($m['role_id'] == $assistantRoleId) {
                $privilegeParams['assistant_id'][] = $m['id'];
            } elseif ($m['role_id'] == $courseManageRoleId) {
                $privilegeParams['course_manage_id'][] = $m['id'];
            }
        }
        return $privilegeParams;
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
        $remarkStatusMap = DictService::getTypeMap(Constants::DICT_TYPE_STUDENT_REMARK_STATUS);

        // 格式化学生数据
        foreach($list as $item){
            $row = [];
            $row['student_id'] = $item['student_id'];
            $row['name'] = $item['name'];
            $row['mobile'] = (($item['country_code'] == StudentModel::DEFAULT_COUNTRY_CODE) ? '' : ($item['country_code'] . '-')) . Util::hideUserMobile($item['mobile']);
            $row['pay_status'] = empty($item['first_pay_time']) ? '未付费' : '已付费';
            //计算过期时间戳
            $expireTime = strtotime($item['sub_end_date'].' 00:00');
            $row['effect_status'] = ($expireTime > $time && $item['sub_status']) ?  '未过期' : '已过期';
            $row['expire_time'] = empty($expireTime) ? '-' : date('Y-m-d', $expireTime);
            $row['student_step'] = isset($stepMap[$item['has_review_course']]) ? $stepMap[$item['has_review_course']] : '-';
            $row['wechat_bind'] = empty($item['wx_id']) ? '未绑定' : '已绑定';
            $row['assistant_name'] = $item['assistant_name'];
            $row['is_add_assistant_wx'] = $item['is_add_assistant_wx'];
            $row['collection_name'] = $item['collection_name'];
            $row['channel'] = $item['channel_name'];
            $row['parent_channel'] = $item['parent_channel_name'];
            $row['register_time'] = date('Y-m-d H:i', $item['create_time']);
            $row['latest_remark_status'] = $remarkStatusMap[$item['latest_remark_status']] ?? '-';
            $row['allot_collection_time'] = empty($item['allot_collection_time']) ? '-' : date('Y-m-d H:i', $item['allot_collection_time']);
            $row['wechat_account'] = $item['wechat_account'];
            $row['course_manage_name'] = $item['course_manage_name'];
            $row['serve_app_id'] = $item['serve_app_id'];
            $data[] = $row;
        }
        return $data;
    }

    /**
     * 格式化列表数据
     * @param $list
     * @return array
     */
    public static function formatCourseListData($list)
    {
        $data = [];
        // 格式化学生数据
        foreach($list as $item){
            $row = [];
            $row['name'] = $item['user_name'];
            $row['mobile'] = Util::hideUserMobile($item['mobile']);
            if ($item['code_status'] == Constants::STATUS_FALSE) {
                $row['code_status'] = "未激活";
            } elseif ($item['code_status'] == Constants::STATUS_TRUE) {
                $row['code_status'] = '已激活';
            } else {
                $row['code_status'] = '已作废';
            }
            $row['is_add_course_wx'] = empty($item['is_add_course_wx']) ? 0 : 1;
            $row['collection_name'] = !empty($item['course_manage_name']) ? $item['course_manage_name'] : '-';
            $row['assistant_name'] = !empty($item['assistant_name']) ? $item['assistant_name'] : '-';
            $row['sub_end_date'] = !empty($item['sub_end_date']) ? date('Y-m-d', strtotime($item['sub_end_date'].' 00:00')) : '-';;
            $row['allot_course_time'] = !empty($item['allot_course_time']) ? date('Y-m-d', $item['allot_course_time']) : '-';
            $row['total_duration'] = !empty($item['total_duration']) ? round($item['total_duration'] / 60, 1) : 0;
            $row['avg_duration'] = !empty($item['avg_duration']) ? round($item['avg_duration'] / 60, 1) : 0;
            $row['play_days'] = !empty($item['play_days']) ? $item['play_days'] : "0";
            $row['bill_amount'] = !empty($item['bill_amount']) ? $item['bill_amount'] / 100 : 0;

            if (empty($item['share_status'])) {
                $row['share_status'] = "未提交";
            } elseif ($item['share_status'] == SharePosterModel::STATUS_WAIT_CHECK) {
                $row['share_status'] = "待审核";
            } elseif ($item['share_status'] == SharePosterModel::STATUS_QUALIFIED) {
                $row['share_status'] = "已通过";
            } else {
                $row['share_status'] = "未通过";
            }
            $row['remark_time'] = !empty($item['remark_time']) ? date('Y-m-d H:i:s', $item['remark_time']) : '-';
            $row['student_id'] = $item['id'];
            $row['serve_app_id'] = $item['serve_app_id'];

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
        //修改学生班级和助教数据
        $cnt = self::updateStudentCollectionAndAssistant($studentIds, $collectionId, $collection['assistant_id'], $time);
        if($cnt != $studentCount){
            return Valid::addErrors([], 'update_student_data_failed', 'update_student_data_failed');
        }
        //同步观单数据
        QueueService::studentSyncWatchList($studentIds);
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
    public static function updateStudentCollectionAndAssistant($studentIds, $collectionId, $assistantId, $time)
    {
        return StudentModel::updateStudentCollectionAndAssistant($studentIds, $collectionId, $assistantId, $time);
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


    /**
     * 学生观单结束时间
     * @param $collectionInfo
     * @return float|int|string
     */
    public static function getWatchEndTime($collectionInfo)
    {
        //学生观单数据: 1.普通班级：关单期=班级开班期的结束日期+14天 2.公海班级:关单期=入班时间+30天
        if ($collectionInfo['type'] == CollectionModel::COLLECTION_TYPE_NORMAL) {
            $watchEndTime = $collectionInfo['teaching_end_time'] + 2 * Util::TIMESTAMP_ONEWEEK;
        } elseif ($collectionInfo['type'] == CollectionModel::COLLECTION_TYPE_PUBLIC) {
            $watchEndTime = $collectionInfo['teaching_end_time'] + Util::TIMESTAMP_THIRTY_DAYS;
        } else {
            $watchEndTime = 0;
        }
        return $watchEndTime;
    }

    /**
     * 获取同步到crm的数据
     * @param $studentIdList
     * @return array|bool
     */
    public static function getStudentSyncData($studentIdList)
    {
        //获取学生基础数据
        $syncData = $normalCourseStudent = $normalCourseFirstPayTime = [];
        $studentData = StudentModel::getRecords(['id' => $studentIdList], [], false);
        if (empty($studentData)) {
            return $syncData;
        }
        //学生观单数据
        $collectionIdList = array_unique(array_column($studentData, 'collection_id'));
        $watchList = [];
        if (!empty($collectionIdList)) {
            $collectionData = CollectionModel::getRecords(['id' => $collectionIdList], ['id', 'teaching_end_time', 'teaching_start_time', 'type'], false);
            foreach ($collectionData as $ck => $cv) {
                $watchList[$cv['id']] = $cv;
                $watchList[$cv['id']]['watch_end_time'] = self::getWatchEndTime($cv);
            }
        }
        //状态信息
        array_walk($studentData, function (&$value) use (&$normalCourseStudent, $watchList) {
            //例子当前状态  0 注册、1 付费体验课、2 付费正式课
            if ($value['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_1980) {
                $normalCourseStudent[] = $value['id'];
                $value['dss_status'] = StudentModel::CRM_AI_LEADS_STATUS_BUY_NORMAL_COURSE;
            } elseif ($value['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_49) {
                $value['dss_status'] = StudentModel::CRM_AI_LEADS_STATUS_BUY_TEST_COURSE;
            } else {
                $value['dss_status'] = StudentModel::CRM_AI_LEADS_STATUS_REGISTER;

            }
        });
        //学生首次购买正式课时间
        if (!empty($normalCourseStudent)) {
            // TODO: use local package data
            $plusPackageIdStr = DictConstants::get(DictConstants::PACKAGE_CONFIG, 'plus_package_id');
            $normalCourseFirstPayTime = array_column(GiftCodeModel::getFirstNormalCourse(implode(',', $normalCourseStudent), $plusPackageIdStr), null, 'buyer');
        }
        //获取学生入班时购买的课包类型
        $packageIdList = array_unique(array_column($studentData, 'allot_course_id'));
        $packageInfo = array_column(PackageExtModel::getRecords(['package_id' => $packageIdList], ['package_id', 'package_type', 'trial_type'], false), null, 'package_id');
        foreach ($studentData as $sk => $sv) {
            $syncData[$sv['id']] = [
                'uuid' => $sv['uuid'],
                'mobile' => $sv['mobile'],
                'channel_id' => $sv['channel_id'],
                'name' => $sv['name'],
                'dss_register_time' => $sv['create_time'],
                'birthday' => is_null($sv['birthday']) ? 0 : $sv['birthday'],
                'gender' => $sv['gender'],
                'dss_watch_end_time' => is_null($watchList[$sv['collection_id']]) ? 0 : $watchList[$sv['collection_id']]['watch_end_time'],
                'dss_first_normal_pay_time' => is_null($normalCourseFirstPayTime[$sv['id']]['first_time']) ? 0 : $normalCourseFirstPayTime[$sv['id']]['first_time'],
                'dss_status' => $sv['dss_status'],
                'teaching_start_time' => empty($sv['collection_id']) ? 0 : $watchList[$sv['collection_id']]['teaching_start_time'],
                'teaching_end_time' => empty($sv['collection_id']) ? 0 : $watchList[$sv['collection_id']]['teaching_end_time'],
                'package_type' => empty($sv['allot_course_id']) ? 0 : $packageInfo[$sv['allot_course_id']]['package_type'],
                'trial_type' => empty($sv['allot_course_id']) ? 0 : $packageInfo[$sv['allot_course_id']]['trial_type'],
            ];
        }
        return $syncData;
    }

    /**
     * dss用户导入真人流转
     * @param $studentID
     * @param $syncStatus
     * @return bool
     * @throws RunTimeException
     */
    public static function syncStudentToCrm($studentID, $syncStatus)
    {
        //获取当前同步状态禁止重复导入
        $studentData = StudentModel::getById($studentID);
        if ($studentData['sync_status'] == StudentModel::SYNC_TO_CRM_DO) {
            $e = new RunTimeException(['sync_stop_repeat_action']);
            throw $e;
        }
        //修改数据
        $updateRes = StudentModel::updateRecord($studentID, ['sync_status' => $syncStatus]);
        if (empty($updateRes)) {
            $e = new RunTimeException(['update_date_failed']);
            throw $e;
        }
        //添加到消息队列
        $syncData = self::getStudentSyncData($studentID);
        $queueRes = QueueService::studentSyncData($syncData[$studentID]);
        if (empty($queueRes)) {
            $e = new RunTimeException(['sync_data_push_queue_error']);
            throw $e;
        }
        return $queueRes;
    }

    /**
     * 获取学生当前状态
     * @param int $studentId
     * @return array $studentStatus
     * @throws RunTimeException
     */
    public static function studentStatusCheck($studentId)
    {
        //获取学生信息
        $studentInfo = StudentModel::getById($studentId);
        $data = [];
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $data['student_info'] = $studentInfo;
        //查看学生是否绑定账户
        $userIsBind = UserWeixinModel::getRecord([
            'user_id' => $studentId,
            'status' => UserWeixinModel::STATUS_NORMAL,
            'user_type' => UserWeixinModel::USER_TYPE_STUDENT,
            'busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
        ], ['id'], false);
        if (empty($userIsBind)) {
            //未绑定
            $data['student_status'] = StudentModel::STATUS_UNBIND;
        } else {
            switch ($studentInfo['has_review_course']) {
                case ReviewCourseModel::REVIEW_COURSE_49:
                    //付费体验课
                    $data['student_status'] = StudentModel::STATUS_BUY_TEST_COURSE;
                    break;
                case ReviewCourseModel::REVIEW_COURSE_1980:
                    //付费正式课
                    $data['student_status'] = StudentModel::STATUS_BUY_NORMAL_COURSE;
                    break;
                default:
                    //注册
                    $data['student_status'] = StudentModel::STATUS_REGISTER;
                    break;
            }
        }
        //返回数据
        return $data;
    }

    /**
     * 学生购买课包分配班级和助教
     * @param $studentId
     * @param $collectionInfo
     * @param $employeeId
     * @param $package
     * @return bool
     */
    public static function allotCollectionAndAssistant($studentId, $collectionInfo, $employeeId, $package)
    {
        //获取学生数据
        $studentInfo = StudentModel::getById($studentId);
        if (empty($studentInfo)) {
            return false;
        }
        //班级和助教数据
        $time = time();
        $update['collection_id'] = $collectionInfo['id'];
        $update['allot_collection_time'] = $time;
        $update['allot_course_id'] = $package['package_id'];
        if (!empty($collectionInfo['assistant_id'])) {
            $update['allot_assistant_time'] = $time;
            $update['assistant_id'] = $collectionInfo['assistant_id'];
        }
        $affectRows = StudentModel::updateRecord($studentInfo['id'], $update, false);
        if (empty($affectRows)) {
            SimpleLogger::error('update_student_collection_fail', [
                '$studentId' => $studentId,
                '$collectionInfo' => $collectionInfo,
                '$employeeId' => $employeeId,
                '$package' => $package,
                '$update' => $update,
            ]);
            return false;
        }
        //添加班级分配日志
        $logData = self::formatAllotCollectionLogData([$studentInfo], $collectionInfo['id'], $employeeId, $time);
        $res = self::addAllotCollectionLog($logData);
        if (empty($res)) {
            SimpleLogger::error('add_allot_collection_log_failed', [
                '$logData' => $logData,
            ]);
            return false;
        }
        //添加助教分配日志
        $logData = self::formatAllotAssistantLogData([$studentInfo], $collectionInfo['assistant_id'], $employeeId, $time);
        $res = self::addAllotAssistantLog($logData);
        if (empty($res)) {
            SimpleLogger::error('add_allot_assistant_log_failed', [
                '$logData' => $logData,
            ]);
            return false;
        }
        //同步观单数据
        QueueService::studentSyncWatchList($studentInfo['id']);
        return true;
    }

    /**
     * 分配课管
     * @param $studentIds
     * @param $courseManageId
     * @param $employeeId
     * @return bool
     * @throws RunTimeException
     */
    public static function allotCourseManage($studentIds, $courseManageId, $employeeId)
    {
        //获取学生信息
        $students = StudentModel::getStudentByIds($studentIds);
        if (empty($students) || count($students) != count($studentIds)) {
            throw new RunTimeException(['student_ids_error']);
        }
        //检测课管状态是否可用
        $roleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_COURSE_MANAGE_ROLE_ID_CODE);
        $courseManageInfo = EmployeeModel::getRecord(['id' => $courseManageId, 'role_id' => $roleId, 'status' => EmployeeModel::STATUS_NORMAL], 'id', false);
        if (empty($courseManageInfo)) {
            throw new RunTimeException(['course_manage_status_disable']);
        }
        //过滤课管无需修改的数据
        $courseManageChangeStudents = [];
        array_map(function ($studentDetail) use ($courseManageId, &$courseManageChangeStudents) {
            if ($studentDetail['course_manage_id'] != $courseManageId) {
                $courseManageChangeStudents[] = $studentDetail['id'];
            }
        }, $students);
        if (empty($courseManageChangeStudents)) {
            throw new RunTimeException(['student_course_manage_no_change']);
        }
        //修改数据
        $time = time();
        $affectRows = StudentModel::batchUpdateRecord(['course_manage_id' => $courseManageId, 'allot_course_time' => $time], ['id' => $courseManageChangeStudents], false);
        if (empty($affectRows)) {
            throw new RunTimeException(['update_student_data_failed']);
        }
        //记录日志
        $time = time();
        $logData = StudentCourseManageLogModel::formatAllotCourseManageLogData($students, $courseManageId, $employeeId, $time);
        $insertRes = StudentCourseManageLogModel::batchInsert($logData);
        if (empty($insertRes)) {
            throw new RunTimeException(['add_allot_course_manage_log_failed']);
        }
        //返回数据
        return true;
    }

    /**
     * 第一次分配课管的学生，进行消息推送
     * @param $course_manage_id
     * @param $courseInfo
     * @param $toBePushedStudentInfo
     */
    public static function allotCoursePushMessage($course_manage_id, $courseInfo, $toBePushedStudentInfo)
    {
        $url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/codePage?id=" . $course_manage_id;
        $templateId = $_ENV["WECHAT_DISTRIBUTION_MANAGEMENT"];
        $data = [
            'first' => [
                'value' => "已为您分配专属服务教师，详情如下："
            ],
            'keyword1' => [
                'value' => "专属服务教师分配"
            ],
            'keyword2' => [
                'value' => "您的专属服务教师为【".$courseInfo['wx_nick']."】"
            ],
            "remark" => [
                "value" => "点击添加专属年卡老师，给您提供赠品邮寄、年卡激活和打谱等服务"
            ],
        ];
        $msgBody = [
            'wx_push_type' => 'template',
            'template_id' => $templateId,
            'data' => $data,
            'url' => $url,
            'open_id' => '',
        ];

        try {
            $topic = new PushMessageTopic();
        } catch (\Exception $e) {
            Util::errorCapture('PushMessageTopic init failure', [
                'dateTime' => time(),
            ]);
        }

        foreach ($toBePushedStudentInfo as $info) {
            $msgBody['open_id'] = $info['open_id'];

            try {
                $topic->wxPushCommon($msgBody)->publish();

            } catch (\Exception $e) {
                SimpleLogger::error("allotCourseManage send failure", ['info' => $info]);
                continue;
            }
        }
    }

    /**
     * 查看学生隐私信息
     * @param $studentIds
     * @param $employeeId
     * @param $field
     * @param $operateType
     * @return mixed
     * @throws RunTimeException
     */
    public static function getStudentSelfSecret($studentIds, $employeeId, $field = [], $operateType = StudentAcquiredLogModel::OPERATE_TYPE_GET_MOBILE)
    {
        //获取学生信息
        $studentInfoList = StudentModel::getRecords(['id' => $studentIds], $field, false);
        if (empty($studentInfoList)) {
            throw new RunTimeException(['student_ids_error']);
        }
        foreach ($studentInfoList as $key => $value){
            $studentInfoList[$key]['mobile'] = (($value['country_code'] == StudentModel::DEFAULT_COUNTRY_CODE) ? '' : ($value['country_code'] . '-')) . $value['mobile'];
        }
        //记录日志
        $time = time();
        $logData = StudentAcquiredLogModel::formatLogData($studentIds, $employeeId, $time, $operateType);
        $insertRes = StudentAcquiredLogModel::batchInsert($logData, false);
        if (empty($insertRes)) {
            throw new RunTimeException(['add_student_acquired_log_failed']);
        }
        return $studentInfoList;
    }

    /**
     * 学生信息模糊搜索
     * @param $params
     * @param $fields
     * @return mixed
     */
    public static function fuzzySearchStudent($params, $fields)
    {
        $where = $data = [];
        //手机号采取完全匹配
        if (!empty($params['mobile'])) {
            $where['mobile'] = $params['mobile'];
        }
        if (empty($where)) {
            return $data;
        }
        $data = StudentModel::getRecords($where, $fields, false);
        if (!empty($data)) {
            foreach ($data as $k => &$v) {
                $v['mobile'] = Util::hideUserMobile($v['mobile']);
            }
        }
        return $data;
    }

    /**
     * 获取学生体验期内练琴天数
     * @param $date
     * @return array
     */
    public static function getExperiencePlayDayCount($date)
    {
        //获取指定时间结班的班级数据
        $data = CollectionService::getCollectionByEndTime($date);
        if(empty($data)){
            return false;
        }
        //按开班、结班日期将班级分组
        $groupData = self::formatCollectionByTime($data);
        //获取班级学生数据
        $groupData = self::getGroupStudent($groupData);
        //获取学生体验期内练琴天数数据
        $res = self::getGroupStudentPlayCount($groupData);
        return $res;
    }

    /**
     * 获取组内学生练琴天数
     * @param $data
     * @return array
     */
    public static function getGroupStudentPlayCount($data)
    {
        $res = [];
        foreach($data as $value){
            if(!empty($value['students'])){
                $groupData = AIPlayRecordService::getStudentPlayCount($value['students'], $value['startDate'], $value['endDate']);
                $formatData = self::formatUuidData($value['students'], $groupData);
                $res = array_merge($res, $formatData);
            }
        }
        return $res;
    }

    /**
     * 根据uuid格式化学生练琴数据
     * @param $students
     * @param $data
     * @return array
     */
    public static function formatUuidData($students, $data)
    {
        $res = [];
        $studentsMap = self::getStudentMap($students);
        foreach($data as $value){
            $item = [];
            $item['uuid'] = $studentsMap[$value['student_id']];
            $item['count'] = $value['num'];
            $res[] = $item;
        }
        return $res;
    }

    /**
     * 获取学生数据map
     * @param $students
     * @return array
     */
    public static function getStudentMap($students)
    {
        $map = [];
        foreach($students as $student){
            $map[$student['id']] = $student['uuid'];
        }
        return $map;
    }

    /**
     * 按照开班、结班日期分组班级数据
     * @param $data
     * @return array
     */
    public static function formatCollectionByTime($data)
    {
        $res = [];
        foreach($data as $item){
            $key = $item['start_date'].'_'.$item['end_date'];
            if(!isset($res[$key])){
                $res[$key]['startDate'] = $item['start_date'];
                $res[$key]['endDate'] = $item['end_date'];
            }
            $res[$key]['ids'][] = $item['id'];
        }
        return $res;
    }

    /**
     * 获取分组学生数据
     * @param $groupCollection
     * @return mixed
     */
    public static function getGroupStudent($groupCollection)
    {
        foreach($groupCollection as $key => $value){
            $groupCollection[$key]['students'] = self::getStudentsByCollection($value['ids']);
        }
        return $groupCollection;
    }

    /**
     * 根据班级ID获取全部学生id
     * @param $collectionIds
     * @return array
     */
    public static function getStudentsByCollection($collectionIds)
    {
        return StudentModel::getStudentIdsByCollection($collectionIds);
    }

    /**
     * 学生支付后事件
     * @param $event
     */
    public static function onPaid($event)
    {
        $student = StudentService::getByUuid($event['uuid']);

        if (empty($student)) {
            SimpleLogger::info("student not found", ['uuid' => $event['uuid']]);
            return ;
        }

        TrackService::studentPaidCallback($student);
    }

    /**
     * 更新外部用户标记
     * @param $student
     * @param $packageInfo
     * @return bool
     */
    public static function updateOutsideFlag($student, $packageInfo)
    {
        if ($student['serve_app_id'] == PackageExtModel::APP_AI
            && !empty($packageInfo['app_id'])
            && $packageInfo['app_id'] != PackageExtModel::APP_AI) {
            StudentModel::updateRecord($student['id'], ['serve_app_id' => $packageInfo['app_id']]);
            return true;
        }
        return false;
    }

    /**
     * @param $text
     * 阿里云检测文本合法
     */
    public static function checkScanText($text)
    {
        //敏感词过滤
        $checkResponse = (new AliContentCheck())->textScan($text);
        if (!empty($checkResponse)) {
            array_map(function ($item) {
                if ($item == AliContentCheck::ILLEGAL_RESULT) {
                    throw new RunTimeException(['illegal_content']);
                }
            }, $checkResponse);
        }
    }

    /**
     * @param $url
     * 检测图片是否合法
     */
    public static function checkScanImg($url)
    {
        $checkResponse = (new AliContentCheck())->checkImgLegal($url);
        if (!empty($checkResponse)) {
            array_map(function ($item) {
                if ($item == AliContentCheck::ILLEGAL_RESULT) {
                    throw new RunTimeException(['illegal_img']);
                }
            }, $checkResponse);
        }
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 返回推荐人列表
     */
    public static function getRefereeStudent($params)
    {
        $refereeInfo = StudentModel::getById($params['student_id']);
        if (!empty($refereeInfo)) {
            $params['referral_mobile'] = $refereeInfo['mobile'];
        } else {
            throw new RunTimeException(['student_not_exist']);
        }

        $recode = $studentList = [];
        $ret = ErpReferralService::getReferredList($params);
        if (!empty($ret)) {
            $recode = array_column($ret['list'], null, 'student_id');
            $studentList = array_keys($recode);
        }

        $studentInfo = StudentModel::getRefereeStudentInfo($studentList);
        if (!empty($studentInfo)) {
            foreach ($studentInfo as $key => $value) {
                $studentInfo[$key]['max_event_task_name'] = $recode[$value['id']]['max_event_task_name'];
                $studentInfo[$key]['mobile'] = Util::hideUserMobile($value['mobile']);
            }
        }
        return [
            'list'        => $studentInfo,
            'total_count' => $ret['total_count'] ?? 0,
        ];
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 获取当前学员的红包列表
     */
    public static function getRefereeRedPacket($params)
    {
        $refereeInfo = StudentModel::getById($params['student_id']);
        if (!empty($refereeInfo)) {
            $params['referrer_mobile'] = $refereeInfo['mobile'];
        } else {
            throw new RunTimeException(['student_not_exist']);
        }
        if (empty($params['award_type'])) {
            $params['award_type'] = ErpReferralService::AWARD_TYPE_CASH;
        }
        return ErpReferralService::getAwardList($params);
    }

    /**
     * @param $params
     * @return array
     * 练琴记录添加分享token
     */
    public static function getRefereeTasks($params)
    {
        $params['play_date_order'] = "DESC";
        list($total, $tasks) = ReviewCourseTaskService::getTasks($params);
        if (!empty($tasks)) {
            foreach ($tasks as $key => $value) {
                $tasks[$key]['student_mobile'] = Util::hideUserMobile($value['student_mobile']);
                $tasks[$key]['play_date'] = date("Y-m-d",strtotime($value['play_date']));
                $tasks[$key]['sum_duration'] = Util::secondToDate($value['sum_duration']);
                $tasks[$key]['review_date'] = date("Y-m-d",strtotime($value['review_date']));
                $tasks[$key]['review_course_report'] = AIPlayReportService::reviewCourseReportUrl($value['id'],$value['play_date'],$params['student_id']);
                $tasks[$key]['review_daily'] = AIPlayReportService::dailyReportUrl($value['play_date'],$params['student_id']);
            }
        }
        return [$total, $tasks];
    }

    /**
     * @param $params
     * @return array
     * @throws RunTimeException
     * 获取当前学员的激活码列表
     */
    public static function getRefereeCode($params)
    {
        $refereeInfo = StudentModel::getById($params['student_id']);
        if (!empty($refereeInfo)) {
            $params['buyer_mobile'] = $refereeInfo['mobile'];
        } else {
            throw new RunTimeException(['student_not_exist']);
        }

        return GiftCodeService::batchGetCode($params);
    }

    /**
     * @param $params
     * @return array
     * 获取订单列表
     * @throws RunTimeException
     */
    public static function getIntellectOrder($params)
    {
        if (!empty($params['pay_low_amount']) && $params['pay_low_amount'] < 0) {
            throw new RunTimeException(['data_error']);
        }
        if (!empty($params['pay_high_amount']) && $params['pay_high_amount'] < 0) {
            throw new RunTimeException(['data_error']);
        }

        $employUuid = $makeOrderList = [];
        $ret = StudentModel::getIntellectOrder($params);
        if (empty($ret['list'])) {
            return $ret;
        }

        foreach ($ret['list'] as $value) {
            $employUuid[] = $value['employee_uuid'];
        }
        if (!empty($employUuid)) {
            $makeOrderList = EmployeeModel::getRecords(['uuid' => $employUuid], ['uuid', 'name']);
        }
        $makeOrderListKey = array_column($makeOrderList, null, "uuid");

        foreach ($ret['list'] as $k => $v) {
            $ret['list'][$k]['student_mobile'] = Util::hideUserMobile($v['student_mobile']);
            $ret['list'][$k]['make_order'] = $makeOrderListKey[$v['employee_uuid']]['name'] ?? '';
            $ret['list'][$k]['code_status'] = $v['code_status'] == StudentModel::CODE_STATUS_DEPRECATED ? "已退费" : "已处理";
            $ret['list'][$k]['buy_time'] = date('Y-m-d H:i:s', $v['buy_time']);
            $ret['list'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            $ret['list'][$k]['bill_amount'] = $v['bill_amount'] / 100;
            $ret['list'][$k]['pay_status'] = "支付成功";
            $ret['list'][$k]['order_type'] = "客户购买课程";
        }
        return [$ret['totalCount'], $ret['list']];
    }

    /**
     * @param $params
     * @return array
     * 获取员工信息
     */
    public static function getEmployee($params)
    {
        $where = [
            'name[~]' => $params['employee_name']
        ];
        return EmployeeModel::getRecords($where, ['uuid', 'name'], false);
    }

    /**
     * 获取学员手机号
     * @param $studentId
     * @return mixed
     */
    public static function getStudentMobile($studentId)
    {
        $where = [
            'id' => $studentId
        ];
        $res = StudentModel::getRecord($where, ['mobile'], false);
        return $res['mobile'] ?? '';
    }
}