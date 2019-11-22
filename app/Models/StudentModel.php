<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

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
    const CHANNEL_BACKEND_ADD = 1; //机构后台添加
    const CHANNEL_WE_CHAT_SCAN = 2; //微信扫码注册
    const CHANNEL_APP_REGISTER = 3; //爱学琴APP注册
    const CHANNEL_ERP_EXCHANGE = 4; //ERP兑换激活码
    const CHANNEL_ERP_ORDER = 5; //ERP购买订单
    const CHANNEL_ERP_TRANSFER = 6; //ERP转单
    const CHANNEL_WEB_REGISTER = 7; //web注册页
    const CHANNEL_EXAM_MINAPP_REGISTER = 8; //音基小程序注册
    const CHANNEL_SPACKAGE_LANDING = 9; //小课包推广页

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
}