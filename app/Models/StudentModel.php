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
use App\Models\ModelV1\ErpPackageV1Model;
use App\Services\ChannelService;
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

    const CODE_STATUS_DEPRECATED = 2;
    const DEFAULT_COUNTRY_CODE = 86;

    const NOT_ACTIVE_TEXT = '未激活';

    const NOT_GIFT_CODE_VOID = 2; //作废激活码

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
    const CHANNEL_REFERRAL = 1220; // 转介绍

    //是否添加助教微信
    const ADD_STATUS = 1;
    const UN_ADD_STATUS = 0;

    //默认首次付费时间
    const DEFAULT_FIRST_PAY_TIME = 0;
    //是否已操作导入到真人1未操作2已操作
    const SYNC_TO_CRM_UNDO = 1;
    const SYNC_TO_CRM_DO = 2;

    //crm数据库中ai_leads表粒子当前状态: 0注册 1付费体验课 2付费正式课
    const CRM_AI_LEADS_STATUS_REGISTER = 0;
    const CRM_AI_LEADS_STATUS_BUY_TEST_COURSE = 1;
    const CRM_AI_LEADS_STATUS_BUY_NORMAL_COURSE = 2;

    //学生的状态
    const STATUS_REGISTER = 0;//注册
    const STATUS_BUY_TEST_COURSE = 1;//付费体验课
    const STATUS_BUY_NORMAL_COURSE = 2;//付费正式课
    const STATUS_UNBIND = 3;//未绑定微信
    const STATUS_BIND = 4;//已绑定微信
    //是否加入排行榜 0未启用排行榜 1启用排行榜 2禁用排行榜
    const STATUS_JOIN_RANKING_DISABLE = 0;
    const STATUS_JOIN_RANKING_ABLE = 1;
    const STATUS_JOIN_RANKING_STOP = 2;

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
            '[>]'.EmployeeModel::$table.'(em)' => ['s.course_manage_id' => 'id'],
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
            's.thumb',
            'e.name(assistant_name)',
            's.is_add_assistant_wx',
            's.wechat_account',
            's.uuid',
            's.sync_status',
            'em.name(course_manage_name)'
        ];
        $where = ['s.id' => $studentId];
        return MysqlDB::getDB()->get($table, $join, $fields, $where);
    }

    /**
     * 获取学生列表数据
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function studentList($params, $page, $count)
    {
        //格式化搜索条件
        list($where, $map) = self::formatSearchParams($params);
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
                LEFT JOIN {$assistant} AS `em` ON `s`.`course_manage_id` = `em`.`id`
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
     * 课管学生列表数据
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function CourseStudentList($params, $page, $count)
    {
        //格式化搜索条件
        list($where, $map) = self::formatCourseSearchParams($params);
        if($where == false){
            return [0, []];
        }
        //定义表
        $student = self::$table;
        $employee = EmployeeModel::$table;
        $remark = StudentRemarkModel::$table;
        $course = ReviewCourseTaskModel::$table;
        $gift = GiftCodeModel::$table;
        $poster = SharePosterModel::$table;

        //变量
        $generateChannel = GiftCodeModel::BUYER_TYPE_ERP_ORDER;
        $AIPLAppId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $PRACTICEAppId = UserCenter::APP_ID_PRACTICE;
        $activityId = $params['activity_id'];
        $type = SharePosterModel::TYPE_UPLOAD_IMG;
        $playStartTime = date('Ymd', strtotime($params['play_start_time']));;
        $playEndTime = date('Ymd', strtotime($params['play_end_time']));

        //定义sql语句
        $table = " FROM {$student} AS `s` ";
        $join = " LEFT JOIN {$remark} AS `sr` on s.last_remark_id = sr.id LEFT JOIN {$employee} AS `ea` on s.assistant_id = ea.id LEFT JOIN {$employee} AS `ec` on s.course_manage_id = ec.id LEFT JOIN (SELECT student_id, SUM(sum_duration) total_duration, COUNT(DISTINCT play_date) play_days, SUM(sum_duration) / COUNT(DISTINCT play_date) avg_duration, play_date FROM {$course} AS `rct` where play_date >= " . $playStartTime . " and play_date <= " . $playEndTime . " GROUP BY student_id) rc ON rc.student_id = s.id LEFT JOIN (select buyer, code_status, bill_amount, ROW_NUMBER() OVER(PARTITION BY buyer ORDER BY buy_time DESC) r from {$gift} where generate_channel = " . $generateChannel . " and (bill_app_id = " . $AIPLAppId . " or bill_app_id = " . $PRACTICEAppId . ")) AS `gf` on r = 1 and  gf.buyer = s.id LEFT JOIN (select student_id, activity_id, `status`, ROW_NUMBER() OVER(PARTITION BY student_id ORDER BY create_time DESC) t from {$poster} where activity_id = " . $activityId . "  and `type` = " . $type . ") AS `sp` on t = 1 and sp.student_id = s.id ";

        //统计数量
        $num = self::getListCount($table, $join, $where, $map);
        if(empty($num)){
            return [0, []];
        }
        $data = self::getStudentListData($table, $join, $where, $map, $page, $count);
        return [$num, $data];
    }

    /**
     * 格式化搜索学生条件
     * @param $params
     * @return array
     */
    public static function formatSearchParams($params)
    {
        $whereSql = ' WHERE 1 ';
        $map = [];
        //学生id
        if (!empty($params['student_id'])) {
            $whereSql .= " AND s.id = :student_id ";
            $map[':student_id'] = $params['student_id'];
        }

        if ($params['no_assistant']) {
            $whereSql .= " AND s.assistant_id = 0 ";
        }

        if ($params['no_course_manage']) {
            $whereSql .= " AND s.course_manage_id = 0 ";
        }

        /**
         * 权限控制
         * 助教查看学生规则：
         *  1. 学生的助教为当前用户
         *  2. 学生所在班级助教为当前用户
         * 课管查看学生规则：
         *  1. 学生的课管为当前用户
         * 其他角色
         *  1. 其他角色能够查看所有学生数据
         */

        if (!empty($params['assistant_id'])) {
            if (is_array($params['assistant_id'])) {
                $assistantIds = implode(', ', $params['assistant_id']);
                $assistantFilter = "s.assistant_id IN ({$assistantIds}) OR co.assistant_id IN ({$assistantIds})";
            } elseif (is_numeric($params['assistant_id'])) {
                $assistantFilter = "s.assistant_id = {$params['assistant_id']} OR co.assistant_id = {$params['assistant_id']}";
            } else {
                $assistantFilter = '';
            }

            if (!empty($assistantFilter)) {
                $whereSql .= " AND ({$assistantFilter}) ";
            }
        }

        if (!empty($params['course_manage_id'])) {
            if (is_array($params['course_manage_id'])) {
                $courseManageIds = implode(', ', $params['course_manage_id']);
                $courseManageFilter = "s.course_manage_id IN ({$courseManageIds})";
            } elseif (is_numeric($params['course_manage_id'])) {
                $courseManageFilter = "s.course_manage_id = {$params['course_manage_id']}";
            } else {
                $courseManageFilter = '';
            }

            if (!empty($courseManageFilter)) {
                $whereSql .= " AND {$courseManageFilter} ";
            }
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
        // 服务业务线
        if (!empty($params['serve_app_id'])) {
            $whereSql .= " AND s.serve_app_id = :serve_app_id ";
            $map[':serve_app_id'] = $params['serve_app_id'];
        }
        return [$whereSql, $map];
    }


    /**
     * 课管学生列表（格式化搜索学生条件）
     * @param $params
     * @return array
     */
    public static function formatCourseSearchParams($params)
    {
        $whereSql = ' WHERE s.has_review_course = :has_review_course ';
        $map = [':has_review_course' => ReviewCourseModel::REVIEW_COURSE_1980];
        //学生id
        if (!empty($params['student_id'])) {
            $whereSql .= " AND s.id = :student_id ";
            $map[':student_id'] = $params['student_id'];
        }

        // 无助教
        if ($params['no_assistant']) {
            $whereSql .= " AND s.assistant_id = 0 ";
        }

        //无课管
        if ($params['no_course_manage']) {
            $whereSql .= " AND s.course_manage_id = 0 ";
        }

        //查看助教、助教组下的用户
        $assistantFilter = '';
        if (!empty($params['assistant_id']) && is_array($params['assistant_id'])) {
            $assistantIds = implode(', ', $params['assistant_id']);
            $assistantFilter = "s.assistant_id IN ({$assistantIds})";
        }
        if (!empty($params['assistant_id']) && is_numeric($params['assistant_id'])) {
            $assistantFilter = "s.assistant_id = {$params['assistant_id']}";
        }
        if (!empty($assistantFilter)) {
            $whereSql .= " AND ({$assistantFilter}) ";
        }

        //查看课管、课管组下的用户
        $courseManageFilter = '';
        if (!empty($params['course_manage_id']) && is_array($params['course_manage_id'])) {
            $courseManageIds = implode(', ', $params['course_manage_id']);
            $courseManageFilter = "s.course_manage_id IN ({$courseManageIds})";
        }
        if (!empty($params['course_manage_id']) && is_numeric($params['course_manage_id'])) {
            $courseManageFilter = "s.course_manage_id = {$params['course_manage_id']}";
        }
        if (!empty($courseManageFilter)) {
            $whereSql .= " AND {$courseManageFilter} ";
        }

        //学生姓名
        if (!empty($params['student_name'])) {
            $whereSql .= " AND s.name like :student_name ";
            $map[':student_name'] = Util::sqlLike($params['student_name']);
        }
        //学生手机号
        if (!empty($params['mobile'])) {
            $whereSql .= " AND s.mobile like :mobile ";
            $map[':mobile'] = Util::sqlLike($params['mobile']);
        }

        //是否添加课管微信
        if (isset($params['is_add_course_wx']) && !Util::emptyExceptZero($params['is_add_course_wx'])) {
            $whereSql .= " AND s.is_add_course_wx = :is_add_course_wx ";
            $map[':is_add_course_wx'] = $params['is_add_course_wx'];
        }

        //激活状态
        if (isset($params['code_status']) && !Util::emptyExceptZero($params['code_status'])) {
            $whereSql .= " AND gf.code_status = :code_status ";
            $map[':code_status'] = $params['code_status'];
        }

        // 服务业务线
        if (!empty($params['serve_app_id'])) {
            $whereSql .= " AND s.serve_app_id = :serve_app_id ";
            $map[':serve_app_id'] = $params['serve_app_id'];
        }

        //分配日期 开始时间
        if (!empty($params['allot_course_start_time'])) {
            $whereSql .= ' AND s.allot_course_time >= :allot_course_start_time ';
            $map[':allot_course_start_time'] = $params['allot_course_start_time'];
        }

        //分配日期 结束时间
        if (!empty($params['allot_course_end_time'])) {
            $whereSql .= ' AND s.allot_course_time <= :allot_course_end_time ';
            $map[':allot_course_end_time'] = $params['allot_course_end_time'];
        }

        //查询有效期开始时间
        if (!empty($params['effect_start_time'])) {
            $whereSql .= " AND s.sub_end_date >= :effect_start_time ";
            $map[':effect_start_time'] = date('Ymd', $params['effect_start_time']);
        }
        //查询有效期结束时间
        if (!empty($params['effect_end_time'])) {
            $whereSql .= " AND s.sub_end_date <= :effect_end_time ";
            $map[':effect_end_time'] = date('Ymd', $params['effect_end_time']);
        }


        // 总时长最小值
        if (isset($params['total_duration_min']) && is_numeric($params['total_duration_min'])) {
            $whereSql .= " AND IFNULL(total_duration, 0) >= :total_duration_min ";
            $map[":total_duration_min"] = $params['total_duration_min'] * 60;
        }
        //总时长最大值
        if (isset($params['total_duration_max']) && is_numeric($params['total_duration_max'])) {
            $whereSql .= " AND IFNULL(total_duration, 0) <= :total_duration_max ";
            $map[":total_duration_max"] = $params['total_duration_max'] * 60;
        }

        // 日均时长最小值
        if (isset($params['avg_duration_min']) && is_numeric($params['avg_duration_min'])) {
            $whereSql .= " AND IFNULL(avg_duration, 0) >= :avg_duration_min ";
            $map[":avg_duration_min"] = $params['avg_duration_min'] * 60;
        }
        // 日均时长最大值
        if (isset($params['avg_duration_max']) && is_numeric($params['avg_duration_max'])) {
            $whereSql .= " AND IFNULL(avg_duration, 0) <= :avg_duration_max ";
            $map[":avg_duration_max"] = $params['avg_duration_max'] * 60;
        }

        // 日报数最小值
        if (isset($params['play_days_min']) && is_numeric($params['play_days_min'])) {
            $whereSql .= " AND IFNULL(play_days, 0) >= :play_days_min ";
            $map[":play_days_min"] = $params['play_days_min'];
        }
        //日报数最大值
        if (isset($params['play_days_max']) && is_numeric($params['play_days_max'])) {
            $whereSql .= " AND IFNULL(play_days, 0) <= :play_days_max ";
            $map[":play_days_max"] = $params['play_days_max'];
        }

        //上次跟进开始时间
        if (!empty($params['remark_start_time'])) {
            $whereSql .= " AND sr.create_time >= :remark_start_time ";
            $map[':remark_start_time'] = $params['remark_start_time'];
        }
        //上次跟进结束时间
        if (!empty($params['remark_end_time'])) {
            $whereSql .= " AND sr.create_time <= :remark_end_time ";
            $map[':remark_end_time'] = $params['remark_end_time'];
        }

        //购买最小值
        if (isset($params['bill_amount_min']) && is_numeric($params['bill_amount_min'])) {
            $whereSql .= " AND gf.bill_amount >= :bill_amount_min ";
            $map[':bill_amount_min'] = $params['bill_amount_min'] * 100;
        }
        //购买最大值
        if (isset($params['bill_amount_max']) && is_numeric($params['bill_amount_max'])) {
            $whereSql .= " AND gf.bill_amount <= :bill_amount_max ";
            $map[':bill_amount_max'] = $params['bill_amount_max'] * 100;
        }

        //朋友圈的分享状态
        if (isset($params['share_status']) && $params['share_status'] != 0) {
            $whereSql .= " AND sp.status = :share_status";
            $map[':share_status'] = $params['share_status'];
        } elseif (isset($params['share_status']) && $params['share_status'] == 0) {
            $whereSql .= " AND sp.status is null";
        }

        $whereSql .= " order by s.id desc ";
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
                       `s`.`country_code`,
                       `s`.`sub_end_date`,
                       `s`.`sub_status`,
                       `s`.`first_pay_time`,
                       `s`.`has_review_course`,
                       `s`.`latest_remark_status`,
                       `s`.`serve_app_id`,
                       `sw`.`id` AS wx_id,
                       `ass`.`name` AS assistant_name,
                       `s`.`is_add_assistant_wx`,
                       `co`.`name` AS collection_name,
                       `ch`.`name` AS channel_name,
                       `pch`.`name` AS parent_channel_name,
                       `s`.`create_time`,
                       `s`.`allot_collection_time`,
                       `s`.`wechat_account`,
                       `em`.`name` AS course_manage_name";

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


    public static function getStudentListData($table, $join, $where, $map, $page, $count)
    {
        //查询字段
        $select  = " select  s.id, s.name user_name, s.mobile, s.serve_app_id, s.sub_end_date, s.allot_course_time, s.is_add_course_wx, sr.create_time remark_time, sr.remark last_remark, ea.name assistant_name, ec.name course_manage_name, rc.total_duration, rc.play_days, rc.avg_duration,rc.play_date, gf.code_status, gf.bill_amount,sp.`status` share_status, sp.activity_id";

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
     * 更新学生班级和助教信息
     * @param $studentIds
     * @param $collectionId
     * @param $assistantId
     * @param $time
     * @return int|null
     */
    public static function updateStudentCollectionAndAssistant($studentIds, $collectionId, $assistantId, $time)
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

    /**
     * @param $uuIdList
     * @param $needFields
     * @return array
     * 学生分配的班级
     */
    public static function getStudentCollectionInfo($uuIdList, $needFields)
    {
        return  MysqlDB::getDB()->select(self::$table . " (s)",
            [
                '[>]' . CollectionModel::$table . "(c)" => ['s.collection_id' => 'id']
            ],
            $needFields,
            [
                's.uuid' => $uuIdList
            ]);
    }

    /**
     * 根据id获取学生记录和工单信息
     * @param $studentId
     * @return array
     */
    public static function getStudentAndSwoById($studentId)
    {
        $student = self::$table;
        $studentWorkOrder = StudentWorkOrderModel::$table;

        //定义sql语句
        $table = " FROM {$student} AS `s` ";
        $select  = " SELECT `s`.`id` AS user_id,
                       `s`.`mobile`,
                       `s`.`sub_end_date`,
                       `s`.`has_review_course`,
                       `swo`.`id` AS swo_id,
                       `swo`.`update_time`,
                       `swo`.`refuse_msg`,
                       `swo`.`estimate_day`,
                       `swo`.`view_guidance`,
                       `swo`.`status` AS swo_status";
        $join = " LEFT JOIN {$studentWorkOrder} AS `swo` ON `s`.`id` = `swo`.`student_id`";
        $where = " where s.status = 1 AND s.id = {$studentId} ORDER BY `swo`.`id` DESC LIMIT 1";
        $sql = $select . $table . $join . $where;
        return MysqlDB::getDB()->queryAll($sql);
    }

    /**
     * @param $where
     * @param array $files
     * @return mixed
     * 根据条件获取单条用户记录
     */
    public static function getSingleStudentInfo($where,$files=[])
    {
        return self::getRecord($where,$files);
    }

    /**
     * 获取未分配课管的学生信息
     * @param $studentIds
     * @return array
     */
    public static function getNoCourseStudent($studentIds)
    {
        return self::getRecords(['id' => $studentIds, 'course_manage_id' => Constants::STATUS_FALSE]);
    }

    /**
     * 根据班级ID获取全部学生id
     * @param $collectionIds
     * @return array
     */
    public static function getStudentIdsByCollection($collectionIds)
    {
        $where = [
            'collection_id' => $collectionIds,
        ];
        return self::getRecords($where, ['id', 'uuid']);
    }

    /**
     * @param $ids
     * @return array
     * 获取学员班级，助教和课管信息
     */
    public static function getRefereeStudentInfo($ids)
    {
        $student = self::$table;
        $assistant = EmployeeModel::$table;
        $courseManager = EmployeeModel::$table;
        $collection = CollectionModel::$table;

        return MysqlDB::getDB()->select($student, [
            "[>]{$assistant}(assistant)"         => ["assistant_id" => "id"],
            "[>]{$courseManager}(courseManager)" => ["course_manage_id" => "id"],
            "[>]{$collection}"                   => ["collection_id" => "id"],
        ], [
            $student . '.id',
            $student . '.name',
            $student . '.mobile',
            'assistant.name(assistant_name)',
            'courseManager.name(course_manager_name)',
            $collection . '.name(collection_name)',
        ], [
            $student . '.id' => $ids,
            "ORDER"          => ["$student.create_time" => "DESC"],
        ]);
    }

    /**
     * @param $params
     * @return array
     * 获取订单列表
     */
    public static function getIntellectOrder($params)
    {
        $student = self::$table;
        $employeeModel = EmployeeModel::$table;
        $collection = CollectionModel::$table;
        $giftCode = GiftCodeModel::$table;
        $erpPackage = ErpPackageModel::$table;
        $package1 = ErpPackageV1Model::$table;

        if (!empty($params['order_id'])) {
            $where["{$giftCode}.parent_bill_id"] = $params['order_id'];
        }

        if (!empty($params['student_id'])) {
            $where["{$student}.id"] = $params['student_id'];
        }

        if (!empty($params['student_name'])) {
            $where["{$student}.name[~]"] = $params['student_name'];
        }

        if (!empty($params['student_mobile'])) {
            $where["{$student}.mobile"] = $params['student_mobile'];
        }

        if (!empty($params['pay_start_time'])) {
            $where["{$giftCode}.buy_time[>]"] = $params['pay_start_time'];
        }

        if (!empty($params['pay_end_time'])) {
            $where["{$giftCode}.buy_time[<]"] = $params['pay_end_time'];
        }

        if (!empty($params['pay_low_amount']) || $params['pay_low_amount'] === "0") {
            $where["{$giftCode}.bill_amount[>=]"] = $params['pay_low_amount'] * 100;
        }

        if (!empty($params['pay_high_amount']) || $params['pay_high_amount'] === "0") {
            $where['AND']["{$giftCode}.bill_amount[<=]"] = $params['pay_high_amount'] * 100;
        }

        if (!empty($params['order_status'])) {
            if ($params['order_status'] == self::CODE_STATUS_DEPRECATED) {
                $where["{$giftCode}.code_status"] = $params['order_status'];
            } else {
                $where["{$giftCode}.code_status[!]"] = self::CODE_STATUS_DEPRECATED;
            }
        }

        if (!empty($params['order_type'])) {
            $where["{$giftCode}.generate_channel"] = $params['order_type'];
        }

        if (!empty($params['create_start_time'])) {
            $where["{$giftCode}.create_time[>]"] = $params['create_start_time'];
        }

        if (!empty($params['create_end_time'])) {
            $where['AND']["{$giftCode}.create_time[<]"] = $params['create_end_time'];
        }

        //成单人
        if (!empty($params['employee_uuid'])) {
            $where["{$giftCode}.employee_uuid"] = $params['employee_uuid'];
        }

        $join = [
            "[><]{$giftCode}"                    => ["id" => "buyer"],
            "[>]{$erpPackage}"                   => ["{$giftCode}.bill_package_id" => "id"],
            "[>]{$package1}"                     => ["{$giftCode}.bill_package_id" => "id"],
            "[>]{$employeeModel}(assistant)"     => ["assistant_id" => "id"],
            "[>]{$employeeModel}(courseManager)" => ["course_manage_id" => "id"],
            "[>]{$collection}"                   => ["collection_id" => "id"],
        ];

        $where["{$giftCode}.generate_channel"] = GiftCodeModel::BUYER_TYPE_ERP_ORDER;
        $totalCount = MysqlDB::getDB()->count($student, $join, ["{$student}.id",], $where);
        if (empty($totalCount)){
            return [[],0];
        }

        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $where['LIMIT'] = [($params['page'] - 1) * $params['count'], $params['count']];
        $where['ORDER'] = ["{$giftCode}.create_time" => "DESC"];

        $list = MysqlDB::getDB()->select($student, $join, [
            "{$student}.id(student_id)",
            "{$giftCode}.parent_bill_id",
            "{$erpPackage}.name(package_name)",
            "{$package1}.name(package_name_v1)",
            "{$student}.name(student_name)",
            "{$student}.mobile(student_mobile)",
            "{$collection}.name(collection_name)",
            "assistant.name(assistant_name)",
            "courseManager.name(course_manager_name)",
            "{$giftCode}.bill_amount",
            "{$giftCode}.code_status",
            "{$giftCode}.buy_time",
            "{$giftCode}.create_time",
            "{$giftCode}.employee_uuid",
        ], $where);

        return [
            'list'       => $list,
            'totalCount' => $totalCount,
        ];
    }
}