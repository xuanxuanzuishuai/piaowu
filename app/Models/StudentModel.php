<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Services\ChannelService;
use App\Services\DictService;
use App\Services\EmployeeService;
use App\Services\WeChatService;

class StudentModel extends Model
{
    public static $table = 'student';
    public static $redisExpire = 1;

    const GENDER_UNKNOWN = 0; // 保密
    const GENDER_MALE = 1; //男
    const GENDER_FEMALE = 2; //女

    const STATUS_NORMAL = 1; //正常
    const STATUS_STOP = 2; //禁用

    const SUB_STATUS_STOP = 0; //禁用
    const SUB_STATUS_NORMAL = 1; //正常

    const NOT_ACTIVE_TEXT = '未激活';

    //添加渠道
    const CHANNEL_UNKNOWN = 0;
    const CHANNEL_BACKEND_ADD = 1225; //机构后台添加
    const CHANNEL_WE_CHAT_SCAN = 1226; //微信扫码注册
    const CHANNEL_APP_REGISTER = 1227; //爱学琴APP注册
    const CHANNEL_ERP_EXCHANGE = 1228; //ERP兑换激活码
    const CHANNEL_ERP_ORDER = 1229; //ERP购买订单
    const CHANNEL_ERP_TRANSFER = 1230; //ERP转单
    const CHANNEL_WEB_REGISTER = 1231; //web注册页
    const CHANNEL_EXAM_MINAPP_REGISTER = 1232; //音基小程序注册
    const CHANNEL_SPACKAGE_LANDING = 1233; //小课包推广页

    //是否添加助教微信
    const ADD_STATUS = 1;
    const UN_ADD_STATUS = 0;

    //默认首次付费时间
    const DEFAULT_FIRST_PAY_TIME = 0;

    /**
     * 更新学生信息
     * @param $studentId
     * @param $data
     * @return int|null affectRow
     */
    public static function updateStudent($studentId, $data)
    {
        $data['update_time'] = time();

        return self::updateRecord($studentId, $data,false);
    }

    /**
     * 添加学生
     * @param $name
     * @param $mobile
     * @param $uuid
     * @param $channelId
     * @param $channelLevel
     * @param $countryCode
     * @param $birthday
     * @param $gender
     * @return int|mixed|null|string
     */
    public static function insertStudent($name, $mobile, $uuid, $channelId = null, $channelLevel = null, $countryCode = null, $birthday = null, $gender = null)
    {
        $data = [];
        $data['name'] = $name;
        $data['mobile'] = $mobile;
        $data['uuid'] = $uuid;
        $data['channel_id'] = $channelId;
        $data['channel_level'] = $channelLevel;
        $data['create_time'] = time();
        // 国家代码
        !empty($countryCode) && $data['country_code'] = $countryCode;
        !empty($birthday) && $data['birthday'] = $birthday;
        $data['gender'] = empty($gender) ? StudentModel::GENDER_UNKNOWN : $gender;
        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    /**
     * 添加学生
     * @param $params
     * @param $uuid
     * @param $operatorId
     * @return int|mixed|null|string
     */
    public static function saveStudent($params, $uuid, $operatorId = 0 )
    {
        $data = ['operator_id' => $operatorId];

        $data['name']          = $params['name'];
        $data['mobile']        = $params['mobile'];
        $data['uuid']          = $uuid;
        $data['create_time']   = time();

        if(!empty($params['gender'])){
            $data['gender'] = $params['gender'];
        }
        if(!empty($params['birthday'])){
            $data['birthday'] = $params['birthday'];
        }
        $data['channel_id'] = $params['channel_id'] ?? 0;

        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    /**
     * 查询所有学生，或者查询指定机构下学生
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function selectStudentByOrg($orgId, $page, $count, $params)
    {
        $db = MysqlDB::getDB();

        $s  = StudentModel::$table;
        $so = StudentOrgModel::$table;
        $t  = TeacherStudentModel::$table;
        $te = TeacherModel::$table;
        $e  = EmployeeModel::$table;
        $ch = ChannelModel::$table;

        $tsBindStatus = TeacherStudentModel::STATUS_NORMAL;

        //按姓名或手机号模糊查询,查询出结果后直接返回
        if(!empty($params['key'])) {
            $key = $params['key'];
            $records = $db->queryAll("select * from {$s} s where s.name like :name or mobile like :mobile", [
                ':name'   => "%{$key}%",
                ':mobile' => "{$key}%",
            ]);
            return [$records, count($records)];
        }

        $limit = Util::limitation($page, $count);
        $where = ' where 1=1 ';
        $map = [];

        if(!empty($params['name'])) {
            $where .= " and s.name like :name ";
            $map[':name'] = "%{$params['name']}%";
        }
        if(!empty($params['mobile'])) {
            $where .= " and s.mobile like :mobile ";
            $map[':mobile'] = "{$params['mobile']}%";
        }
        if(!empty($params['channel_id'])) {
            $where .= ' and s.channel_id = :channel_id ';
            $map[':channel_id'] = $params['channel_id'];
        }
        if(isset($params['sub_status'])) {
            $where .= ' and s.sub_status = :sub_status ';
            $map[':sub_status'] = $params['sub_status'];
        }
        if(!empty($params['start_create_time'])) {
            $where .= ' and s.create_time >= :start_create_time ';
            $map[':start_create_time'] = $params['start_create_time'];
        }
        if(!empty($params['end_create_time'])) {
            $where .= ' and s.create_time <= :end_create_time';
            $map[':end_create_time'] = $params['end_create_time'];
        }
        if(!empty($params['sub_status']) && !empty($params['sub_start_date'])) {
            $where .= ' and unix_timestamp(s.sub_start_date) >= :sub_start_date';
            $map[':sub_start_date'] = $params['sub_start_date'];
        }
        if(!empty($params['sub_status']) && !empty($params['sub_end_date'])) {
            $where .= ' and unix_timestamp(s.sub_end_date) <= :sub_end_date';
            $map[':sub_end_date'] = $params['sub_end_date'];
        }
        if(isset($params['gender'])) {
            $where .= ' and s.gender = :gender';
            $map[':gender'] = $params['gender'];
        }
        if(!empty($params['id'])) {
            $where .= ' and s.id = :id ';
            $map[':id'] = $params['id'];
        }

        if ($orgId > 0) {
            $where .= " and so.org_id = :org_id ";
            $map[':org_id'] = $orgId;

            if(!empty($params['is_bind'])) {
                $where .= " and so.status = :is_bind ";
                $map[':is_bind'] = $params['is_bind'];
            }
            // 绑定销售筛选
            if (isset($params['cc_id'])) {
                $where .= " and so.cc_id = :cc_id ";
                $map[':cc_id'] = $params['cc_id'];
            }

            // 是否付费
            if (isset($params['pay_status'])) {
                if ($params['pay_status'] == 1) {
                    $where .= " and so.first_pay_time > 0 ";
                } elseif ($params['pay_status'] == 0) {
                    $where .= " and so.first_pay_time is null ";
                }
            }

            // 绑定老师筛选
            if (isset($params['teacher_id'])) {
                if ($params['teacher_id'] == 0) {
                    $where .= " and te.id is null ";
                } elseif ($params['teacher_id'] > 0) {
                    $where .= " and te.id = :teacher_id ";
                    $map[':teacher_id'] = $params['teacher_id'];
                }
            }


            $sql = "select s.*, t.teacher_id, te.name teacher_name,
                t.status ts_status, so.status bind_status, so.cc_id, so.first_pay_time, e.name cc_name, ch.name channel_name
                from {$s} s
                inner join {$so} so on s.id = so.student_id
                left join {$t} t on s.id = t.student_id and t.org_id = so.org_id and t.status = {$tsBindStatus}
                left join {$te} te on te.id = t.teacher_id 
                left join {$e} e on e.id = so.cc_id
                left join {$ch} ch on ch.id = s.channel_id
                {$where}
                order by s.create_time desc {$limit}";

            $countSql = "select count(*) count
                from {$s} s
                inner join {$so} so on s.id = so.student_id
                left join {$t} t on s.id = t.student_id and t.org_id = so.org_id and t.status = {$tsBindStatus}
                left join {$te} te on te.id = t.teacher_id
                left join {$e} e on e.id = so.cc_id
                {$where}";
        } else {
            $sql = "select s.*, ch.name channel_name
                from {$s} s
                left join {$ch} ch on ch.id = s.channel_id
                {$where}
                order by s.create_time desc {$limit}";

            $countSql = "select count(*) count from {$s} s {$where}";
        }

        $total = $db->queryAll($countSql, $map);

        if (empty($total) || $total[0]['count'] == 0) {
            return [[], 0];
        }

        $records = $db->queryAll($sql, $map);

        return [$records, $total[0]['count']];
    }

    /**
     * 查询一条指定机构和学生id的记录
     * @param $orgId
     * @param $studentId
     * @param $status null 表示不限制状态
     * @return array|null
     */
    public static function getOrgStudent($orgId, $studentId, $status = null)
    {
        $db = MysqlDB::getDB();
        $s = StudentModel::$table;
        $so = StudentOrgModel::$table;

        if(empty($orgId)) {
            $sql = "select s.* from {$s} s where s.id = {$studentId} ";
        } else {
            $sql = "select s.* from {$s} s, {$so} so where s.id = so.student_id and s.id = {$studentId} and so.org_id = {$orgId} ";
            if(!is_null($status)) {
                $sql .= " and so.status = {$status} ";
            }
        }

        $records = $db->queryAll($sql);

        return empty($records) ? [] : $records[0];
    }

    /**
     * 模糊查询，根据学生姓名或者手机号
     * 姓名模糊匹配，手机号等于匹配
     * @param $orgId
     * @param $key
     * @param $roleId
     * @return array|null
     */
    public static function fuzzySearch($orgId, $key, $roleId = null)
    {
        $db = MysqlDB::getDB();

        $s  = StudentModel::$table;
        $so = StudentOrgModel::$table;
        $st = StudentOrgModel::STATUS_NORMAL;
        $e  = EmployeeModel::$table;

        if(!empty(preg_match('/^1\d{10}$/', $key))) {
            if(empty($roleId)) {
                $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so where s.id = so.student_id
                        and so.status = {$st} and so.org_id = {$orgId} and s.mobile = {$key}";
            } else {
                $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so, {$e} e where s.id = so.student_id
                        and so.status = {$st} and so.org_id = {$orgId} and s.mobile = {$key} and e.id = so.cc_id
                        and e.role_id = {$roleId}";
            }
        } else {
            if(empty($roleId)) {
                $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so where s.id = so.student_id
                    and so.status = {$st} and so.org_id = {$orgId} and s.name like '%{$key}%'";
            } else {
                $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so, {$e} e where s.id = so.student_id
                    and so.status = {$st} and so.org_id = {$orgId} and e.id = so.cc_id and e.role_id = {$orgId}
                    and s.name like '%{$key}%'";
            }
        }

        $records = $db->queryAll($sql);

        return $records;
    }

    /**
     * 查询在指定id数组内的学生
     * @param $orgId
     * @param $studentIds
     * @return array
     */
    public static function selectOrgStudentsIn($orgId, $studentIds)
    {
        $db = MysqlDB::getDB();
        $records = $db->select(self::$table . '(s)', [
            '[><]' . StudentOrgModel::$table . '(so)' => ['s.id' => 'student_id']
        ], [
            's.id',
        ], [
            'so.org_id' => $orgId,
            's.id'      => $studentIds,
        ]);

        return $records;
    }

    /**
     * 获取学生数据详情
     * @param $studentId
     * @return array
     */
    public static function getStudentDetail($studentId)
    {
        $table = self::$table.'(s)';
        $join = [
            '[>]'.CollectionModel::$table.'(c)' => ['s.collection_id' => 'id'],
            '[>]'.EmployeeModel::$table.'(e)' => ['s.assistant_id' => 'id'],
        ];
        $fields = [
            's.id',
            's.mobile',
            's.name',
            's.collection_id',
            'c.name(collection_name)',
            's.first_pay_time',
            's.channel_id',
            's.sub_end_date',
            's.sub_status',
            's.create_time',
            's.has_review_course',
            's.assistant_id',
            'e.name(assistant_name)',
            's.is_add_assistant_wx',
            's.wechat_account',
            's.uuid'
        ];
        $where = ['s.id' => $studentId];
        return MysqlDB::getDB()->get($table, $join, $fields, $where);
    }

    /**
     * 获取学生列表数据
     * @param $params
     * @param $page
     * @param $count
     * @param $employeeId
     * @return array
     */
    public static function studentList($params, $page, $count, $employeeId)
    {
        //格式化搜索条件
        list($where, $map) = self::formatSearchParams($params, $employeeId);
        if($where == false){
            return [0, []];
        }

        //定义表
        $student = self::$table;
        $studentWeChat = UserWeixinModel::$table;
        $collection = CollectionModel::$table;
        $channel = ChannelModel::$table;
        $assistant = EmployeeModel::$table;

        //定义sql语句
        $table = " FROM {$student} AS `s` ";
        $join = " LEFT JOIN {$studentWeChat} AS `sw` ON `s`.`id` = `sw`.`user_id` 
                        AND `sw`.`app_id` = ".UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT."
                        AND `sw`.`user_type` = ".WeChatService::USER_TYPE_STUDENT."
                        AND `sw`.`busi_type` = ".UserWeixinModel::BUSI_TYPE_STUDENT_SERVER."
                        AND `sw`.status = ".UserWeixinModel::STATUS_NORMAL."
                LEFT JOIN {$collection} AS `co` ON `s`.`collection_id` = `co`.`id`
                LEFT JOIN {$assistant} AS `ass` ON `s`.`assistant_id` = `ass`.`id`
                LEFT JOIN {$channel} AS `ch` ON `s`.`channel_id` = `ch`.`id` 
                LEFT JOIN {$channel} AS `pch` ON `ch`.`parent_id` = `pch`.`id` ";

        //统计数量
        $num = self::getListCount($table, $join, $where, $map);
        if(empty($num)){
            return [0, []];
        }
        $data = self::getListData($table, $join, $where, $map, $params, $page, $count);
        return [$num, $data];
    }

    /**
     * 格式化搜索学生条件
     * @param $params
     * @param $employeeId
     * @return array
     */
    public static function formatSearchParams($params, $employeeId)
    {
        $whereSql = '';
        $map = [];
        /**
         * 权限控制
         * 助教查看学生规则：
         *  1. 学生的助教为当前用户
         *  2. 学生所在班级助教为当前用户
         * 其他角色
         *  1. 其他角色能够查看所有学生数据
         */
        //获取操作人数据
        $employee = EmployeeService::getById($employeeId);
        if(empty($employee)){
            return [false, false];
        }
        //获取助教 ROLE_ID
        $assistantRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_ASSISTANT);
        if($employee['role_id'] == $assistantRoleId){
            $whereSql .= " WHERE (s.assistant_id = {$employeeId} OR co.assistant_id = {$employeeId}) ";
        }else{
            $whereSql .= " WHERE 1 ";
        }
        //学生姓名
        if(!empty($params['student_name'])){
            $whereSql .= " AND s.name like :student_name ";
            $map[':student_name'] = Util::sqlLike($params['student_name']);
        }
        //学生手机号
        if(!empty($params['mobile'])){
            $whereSql .= " AND s.mobile like :mobile ";
            $map[':mobile'] = Util::sqlLike($params['mobile']);
        }
        //付费状态
        if(isset($params['pay_status']) && !Util::emptyExceptZero($params['pay_status'])){
            if($params['pay_status'] == 0){
                $whereSql .= " AND s.first_pay_time = ".self::DEFAULT_FIRST_PAY_TIME;
            }else{
                $whereSql .= " AND s.first_pay_time != ".self::DEFAULT_FIRST_PAY_TIME;
            }
        }
        //当前阶段
        if(isset($params['current_step']) && !Util::emptyExceptZero($params['current_step'])){
            $whereSql .= " AND s.has_review_course = :current_step ";
            $map[':current_step'] = $params['current_step'];
        }
        //微信绑定状态
        if(isset($params['wx_bind_status']) && !Util::emptyExceptZero($params['wx_bind_status'])){
            if($params['wx_bind_status'] == 0){
                $whereSql .= " AND sw.id IS NULL ";
            }else{
                $whereSql .= " AND sw.id IS NOT NULL ";
            }
        }
        //有效期状态
        if(isset($params['effect_status']) && !Util::emptyExceptZero($params['effect_status'])){
            //获取当前时间年月日格式时间 例：20200202
            $currentData = date('Ymd');
            if($params['effect_status'] == 0){
                $whereSql .= " AND s.sub_end_date >= {$currentData} ";
            }else{
                $whereSql .= " AND s.sub_end_date < {$currentData} ";
            }
        }
        //是否添加助教微信
        if(isset($params['is_add_assistant_wx']) && !Util::emptyExceptZero($params['is_add_assistant_wx'])){
            $whereSql .= " AND s.is_add_assistant_wx = :is_add_assistant_wx ";
            $map[':is_add_assistant_wx'] = $params['is_add_assistant_wx'];
        }
        //所属班级
        if(!empty($params['collection_id'])){
            $whereSql .= " AND s.collection_id = :collection_id ";
            $map[':collection_id'] = $params['collection_id'];
        }
        //所属助教
        if(!empty($params['assistant_id'])){
            $whereSql .= " AND s.assistant_id = :assistant_id ";
            $map[':assistant_id'] = $params['assistant_id'];
        }
        //查询渠道
        if(!empty($params['channel_id'])){
            $whereSql .= " AND s.channel_id = :channel_id ";
            $map[':channel_id'] = $params['channel_id'];
        }elseif(!empty($params['parent_channel_id'])){
            $channels = ChannelService::getChannels($params['parent_channel_id']);
            if(empty($channels)){
                $whereSql .= " AND s.channel_id = :channel_id ";
                $map[':channel_id'] = $params['parent_channel_id'];
            }else{
                $channels[] = $params['parent_channel_id'];
                $ids = implode(',', array_column($channels, 'id'));
                $whereSql .= " AND s.channel_id in ({$ids}) ";
            }
        }
        //查询有效期开始时间
        if(!empty($params['effect_start_time'])){
            $whereSql .= " AND s.sub_end_date >= :effect_start_time ";
            $map[':effect_start_time'] = date('Ymd', $params['effect_start_time']);
        }
        //查询有效期结束时间
        if(!empty($params['effect_end_time'])){
            $whereSql .= " AND s.sub_end_date <= :effect_end_time ";
            $map[':effect_end_time'] = date('Ymd', $params['effect_end_time']);
        }
        //查询注册开始时间
        if(!empty($params['register_start_time'])){
            $whereSql .= " AND s.create_time >= :register_start_time ";
            $map[':register_start_time'] = $params['register_start_time'];
        }
        //查询注册结束时间
        if(!empty($params['register_end_time'])){
            $whereSql .= " AND s.create_time <= :register_end_time ";
            $map[':register_end_time'] = $params['register_end_time'];
        }
        //学员微信号
        if (!empty($params['wechat_account'])) {
            $whereSql .= " AND s.wechat_account like :wechat_account ";
            $map[':wechat_account'] = Util::sqlLike($params['wechat_account']);
        }
        if (!empty($params['latest_remark_status'])) {
            $whereSql .= " AND s.latest_remark_status = :latest_remark_status ";
            $map[':latest_remark_status'] = $params['latest_remark_status'];
        }
        //查询入班时间开始时间
        if (!empty($params['allot_collection_start_time'])) {
            $whereSql .= " AND s.allot_collection_time >= :allot_collection_start_time ";
            $map[':allot_collection_start_time'] = $params['allot_collection_start_time'];
        }
        //查询入班时间结束时间
        if (!empty($params['allot_collection_end_time'])) {
            $whereSql .= " AND s.allot_collection_time <= :allot_collection_end_time ";
            $map[':allot_collection_end_time'] = Util::getStartEndTimestamp($params['allot_collection_end_time'])[1];
        }
        return [$whereSql, $map];
    }

    /**
     * 获取学生列表count
     * @param $table
     * @param $join
     * @param $where
     * @param $map
     * @return mixed
     */
    public static function getListCount($table, $join, $where, $map)
    {
        $select = " SELECT count(s.id) as count ";
        $sql = $select . $table .$join . $where;
        $data = MysqlDB::getDB()->queryAll($sql, $map);
        return $data[0]['count'];
    }

    /**
     * 获取学生列表数据
     * @param $table
     * @param $join
     * @param $where
     * @param $map
     * @param $params
     * @param $page
     * @param $count
     * @return array|null
     */
    public static function getListData($table, $join, $where, $map, $params, $page, $count)
    {
        //查询字段
        $select  = " SELECT `s`.`id` AS student_id,
                       `s`.`mobile`,
                       `s`.`name`,
                       `s`.`sub_end_date`,
                       `s`.`sub_status`,
                       `s`.`first_pay_time`,
                       `s`.`has_review_course`,
                       `s`.`latest_remark_status`,
                       `sw`.`id` AS wx_id,
                       `ass`.`name` AS assistant_name,
                       `s`.`is_add_assistant_wx`,
                       `co`.`name` AS collection_name,
                       `ch`.`name` AS channel_name,
                       `pch`.`name` AS parent_channel_name,
                       `s`.`create_time`,
                       `s`.`allot_collection_time`,
                       `s`.`wechat_account`";

        //排序条件:默认按照注册时间倒叙
        $orderSql = ' ORDER BY ';
        $allotCollectionTimeSortRule = strtolower($params['order_field']['allot_collection_time']);
        if ($allotCollectionTimeSortRule == 'desc') {
            $orderSql .= "s.allot_collection_time " . $allotCollectionTimeSortRule . ",";
        } elseif ($allotCollectionTimeSortRule == 'asc') {
            $nowTime = time();
            $select .= ',if(`s`.`allot_collection_time`=0,' . $nowTime . ',`s`.`allot_collection_time`) as actime';
            $orderSql .= "actime " . $allotCollectionTimeSortRule . ",";
        }
        $where .= $orderSql . ' s.id desc';
        //分页
        if ($page > 0 && $count > 0) {
            $where .= " LIMIT " . ($page - 1) * $count . "," . $count;
        }
        $sql = $select . $table . $join . $where;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    /**
     * 根据ids获取学员信息
     * @param $ids
     * @return array
     */
    public static function getStudentByIds($ids)
    {
        return self::getRecords(['id' => $ids]);
    }

    /**
     * 更新学生集合信息
     * @param $studentIds
     * @param $collectionId
     * @param $assistantId
     * @param $time
     * @return int|null
     */
    public static function updateStudentCollection($studentIds, $collectionId, $assistantId, $time)
    {
        $data = [
            'collection_id' => $collectionId,
            'allot_collection_time' => $time,
            'assistant_id' => $assistantId,
            'allot_assistant_time' => $time
        ];
        $where = [
            'id' => $studentIds
        ];
        return StudentModel::batchUpdateRecord($data, $where);
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
        $data = [
            'assistant_id' => $assistantId,
            'allot_assistant_time' => $time
        ];
        $where = [
            'id' => $studentIds
        ];
        return StudentModel::batchUpdateRecord($data, $where);
    }

    /**
     * 获取班级学生数
     * @param $collectionId
     * @return number
     */
    public static function getCollectionStudentCount($collectionId)
    {
        $where['collection_id'] = $collectionId;
        return MysqlDB::getDB()->count(self::$table, '*', $where);
    }
}