<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/25
 * Time: 12:07 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;

class TeacherModel extends Model
{
    const STATUS_NORMAL = 1;
    const STATUS_STOP = 0;

    public static $table = "teacher";
    public static $redisExpire = 1;
    public static $redisDB;

    //老师导入头像处理队列
    public static $teacher_thumb_handle = "teacher_thumb_list";

    const GENDER_MALE = 1; //男
    const GENDER_FEMALE = 2; //女
    const GENDER_UNKNOWN = 0; // 保密/未知
    /**
     * 定义老师状态相关代码
     * 1注册，2待入职，3在职，4冻结，5离职，6辞退，7不入职
     */
    const ENTRY_REGISTER = 1;
    const ENTRY_WAIT = 2;
    const ENTRY_ON = 3;
    const ENTRY_FROZEN = 4;
    const ENTRY_LEAVE = 5;
    const ENTRY_DISMISS = 6;
    const ENTRY_NO = 7;

    // 定义请假状态 0取消请假，1请假
    const LEAVE_CANCEL = 0;
    const LEAVE_ON = 1;

    //老师类型，1兼职-固定，2兼职-非固定，3 OBT, 4 全职,5 新兼职
    const PART_TIME_FIXED = 1;
    const PART_TIME_UNFIXED = 2;
    const OBT = 3;
    const ALL_TIME = 4;
    const NEW_PART_IME = 5;

    //教授级别，1启蒙，2标准，3资深，4高级，5特级
    const LEVEL_INITIATE = 1;
    const LEVEL_STANDARD = 2;
    const LEVEL_SENIOR = 3;
    const LEVEL_HIGH_GRADE = 4;
    const LEVEL_SUPER = 5;

    //演奏水平，1车尔尼599，2车尔尼849，3车尔尼299，4车尔尼740
    const CZERNY_599 = 1;
    const CZERNY_849 = 2;
    const CZERNY_299 = 3;
    const CZERNY_740 = 4;

    //正式课体验课名称
    const COURSE_FORMAL_NAME = "正式课";
    const COURSE_EXPERIENCE_NAME = "体验课";

    //正式课、体验课代码
    const COURSE_TYPE_NONE = '00';   // 无法上课
    const COURSE_FORMAL_CODE = "01"; //正式课
    const COURSE_EXPERIENCE_CODE = "10"; //体验课
    const COURSE_EXPERIENCE_AND_FORMAL = "11"; //体验课+正式课

    /**
     * 根据sql获取记录总数
     * @param $sql
     * @param $map
     * @return array|null
     */
    protected static function getRecordCountBySql($sql, $map)
    {
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * 根据sql获取分页数据
     * @param $sql
     * @param string $map
     * @return array|null
     */
    public static function getRecordBySql($sql, $map = "")
    {
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * 获取入职老师列表，排除注册、待入职、不入职老师之外的所有老师
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @param $ta_role_id
     * @return array
     */
    public static function getTeacherList($orgId, $page, $count, $params, $ta_role_id)
    {
        $t = TeacherModel::$table;
        $to = TeacherOrgModel::$table;

        if(empty($orgId)) {
            $sql_list = "select t.* from {$t} t ";
            $sql_count = "select count(t.id) as totalCount from {$t} t";
        } else {
            $sql_list = "select t.*,tr.status bind_status from {$t} t inner join {$to} tr on 
                    tr.teacher_id = t.id and tr.org_id = {$orgId} ";
            $sql_count = "select count(t.id) as totalCount from {$t} t inner join {$to} tr on 
                    tr.teacher_id = t.id and tr.org_id = {$orgId} ";
        }

        list($where, $map) = self::whereForEntry($params, $ta_role_id);

        //排序
        if (!empty($params['sort'])){
            $sort = $params['sort'];
            if (is_array($sort)){
                if (!empty($sort[0]) && !empty($sort[1])){
                    $order = " order by ".$sort[0]." ".strtoupper($sort[1]);
                }
            }
            $order = empty($order) ? " order by t.id DESC ":$order;
        }else{
            $order = " order by t.id DESC ";
        }

        $limit = " limit " . ($page - 1) * $count . ", " . $count;

        $sql_list .= $where . $order . $limit;
        $sql_count .= $where;

        $totalCount = self::getRecordCountBySql($sql_count, $map);
        if ($totalCount == 0) {
            return [[], 0];
        }
        $teachers = self::getRecordBySql($sql_list, $map);

        return [$teachers, $totalCount];
    }

    /**
     * 在职老师搜索条件
     * @param $params
     * @param $ta_role_id
     * @return array
     */
    public static function whereForEntry($params, $ta_role_id = '')
    {
        /** 以下是老师的搜索条件 */
        $where = ' where 1=1 ';
        $map = [];

        //是否已经绑定机构
        if(!empty($params['is_bind'])) {
            $where .= " AND tr.status = :is_bind ";
            $map[':is_bind'] = $params['is_bind'];
        }
        // 姓名/手机号/ID
        if (!empty($params['name_id'])) {
            $where .= " AND (t.id like :name_id or t.name like :name_id or mobile like :name_id) ";
            $map[':name_id'] = "%".$params['name_id']."%";
        }

        //性别
        if (isset($params['gender'])) {
            $where .= " AND t.gender = :gender ";
            $map[':gender'] = $params['gender'];
        }
        // 老师类型
        if (!empty($params['type'])) {
            $where .= " AND t.type = :type ";
            $map[':type'] = $params['type'];
        }
        // 教授级别
        if (!empty($params['level'])) {
            $where .= " AND t.level = :level ";
            $map[':level'] = $params['level'];
        }

        // 毕业院校
        if (!empty($params['college_id'])) {
            $where .= " AND t.college_id = :college_id ";
            $map[':college_id'] = $params['college_id'];
        }
        // 所属专业
        if (!empty($params['major_id'])) {
            $where .= " AND t.major_id = :major_id ";
            $map[':major_id'] = $params['major_id'];
        }
        //是否毕业
        if (isset($params['is_graduate']) && $params['is_graduate'] != '') {
            //将毕业年月与当前年月比较大小
            if ($params['is_graduate'] == '1') {
                $where .= " AND t.graduation_date >= " . date('Ym');
            } else {
                $where .= " AND t.graduation_date < " . date('Ym');
            }
        }
        //老师状态
        if (!empty($params['status'])) {
            $where .= " AND t.status = :status ";
            $map[':status'] = $params['status'];
        }

        // 创建时间开始
        if (!empty($params['create_time_start'])) {
            $where .= " AND t.create_time >= :create_time_start ";
            $map[':create_time_start'] = strtotime($params['create_time_start']);
        }
        // 创建时间结束
        if (!empty($params['create_time_end'])) {
            $where .= " AND t.create_time < :create_time_end ";
            $map[':create_time_end'] = strtotime($params['create_time_end']);
        }
        // 首次入职时间开始
        if (!empty($params['first_entry_time_start'])) {
            $where .= " AND t.first_entry_time >= :first_entry_time_start ";
            $map[':first_entry_time_start'] = strtotime($params['first_entry_time_start']);
        }
        // 首次入职时间结束
        if (!empty($params['first_entry_time_end'])) {
            $where .= " AND t.first_entry_time < :first_entry_time_end ";
            $map[':first_entry_time_end'] = strtotime($params['first_entry_time_end']);
        }
        // 最后上课时间开始
        if (!empty($params['last_class_time_start'])) {
            $where .= " AND t.last_class_time >= :last_class_time_start ";
            $map[':last_class_time_start'] = strtotime($params['last_class_time_start']);
        }
        // 最后上课时间结束
        if (!empty($params['last_class_time_end'])) {
            $where .= " AND t.last_class_time < :last_class_time_end ";
            $map[':last_class_time_end'] = strtotime($params['last_class_time_end']);
        }

        return [$where, $map];
    }

    /**
     * 查询指定机构下老师
     * @param $orgId
     * @param $teacherId
     * @return array|null
     */
    public static function getOrgTeacherById($orgId, $teacherId)
    {
        $db = MysqlDB::getDB();
        $records = $db->queryAll("select t.*,o.org_id from teacher t,teacher_org o
        where t.id = o.teacher_id and o.org_id = :org_id and t.id = :id",[':org_id' => $orgId,':id' => $teacherId]);
        return empty($records) ? [] : $records[0];
    }

    /**
     * 模糊查询老师，根据姓名或手机号
     * 姓名模糊匹配，手机号等于匹配
     * @param $orgId
     * @param $key
     * @return array|null
     */
    public static function fuzzySearch($orgId, $key)
    {
        $db = MysqlDB::getDB();
        $t = TeacherModel::$table;
        $tm = TeacherOrgModel::$table;
        $st = TeacherOrgModel::STATUS_NORMAL;

        if(!empty(preg_match('/^1\d{10}$/', $key))) {
            $sql = "select t.id,t.name,t.mobile,tm.org_id from {$t} t,{$tm} tm where t.id = tm.teacher_id
        and tm.status = {$st} and tm.org_id = {$orgId} and t.mobile = {$key}";
        } else {
            $sql = "select t.id,t.name,t.mobile,tm.org_id from {$t} t,{$tm} tm where t.id = tm.teacher_id
        and tm.status = {$st} and tm.org_id = {$orgId} and t.name like '%{$key}%'";
        }

        $records = $db->queryAll($sql);

        return $records;
    }
}