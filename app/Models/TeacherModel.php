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
    public static $table = "teacher_base_info";
    public static $redisExpire = 1;
    public static $redisDB;

    //老师导入头像处理队列
    public static $teacher_thumb_handle = "teacher_thumb_list";

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
     * 验证手机号是否已存在
     * @param $mobile
     * @return bool
     */
    public static function isMobileExist($mobile)
    {
        $db = MysqlDB::getDB();
        $result = $db->has(self::$table, ['mobile' => $mobile]);
        return $result;
    }

    /**
     * 导入数据验证手机号是否存在
     * @param $mobile
     * @return bool
     */
    public static function isMobileExistImport($mobile)
    {
        $db = MysqlDB::getDB();
        $result = $db->has(self::$table, ['mobile' => $mobile, 'status[!]' => self::ENTRY_REGISTER]);
        return $result;
    }

    /**
     * 根据手机号获取记录
     * @param $mobile
     * @return mixed
     */
    public static function getRecordByMobile($mobile)
    {
        $where = ['mobile' => $mobile];
        $db = MysqlDB::getDB();
        return $db->get(static::$table, '*', $where);
    }

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
     * @param $operator_id
     * @param $page
     * @param $count
     * @param $params
     * @param $ta_role_id
     * @return array
     */
    public static function getTeacherList($operator_id, $page, $count, $params, $ta_role_id)
    {
        $sql_list = "select t.*, " .
            " co.college_name as college_name ".
            " from " . TeacherModel::$table . " as t " ;
            //" left join " . TeacherCollegeModel::$table . " as co on t.college_id = co.id ";
        $sql_count = "select count(t.id) as totalCount from " . TeacherModel::$table . " as t ";

        list($where, $map) = self::whereForEntry($params, $operator_id, $ta_role_id);

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
     * 导出注册老师
     * @param $params
     * @return array|null
     */
    public static function exportEntryTeacher($params)
    {
        $sql_list = "select t.*, " .
            " co.college_name as college_name, m.major_name as major_name, ch.name channel_name, group_concat(distinct concat(ta.app_id, '-', app.name, '-', ta.ts_course_type, '-', ta.evaluate_level)) ta_info , 
            group_concat(distinct tag.name) tags " .
            " from " . TeacherModel::$table . " as t " .
            " left join " . TeacherChannelModel::$table . " as ch on ch.id = t.channel_id " .
            " left join " . TeacherAppExtendModel::$table . " as ta on ta.teacher_id = t.id " .
            " left join " . TeacherTagRelationModel::$table . " as tl on tl.teacher_id = t.id " .
            " left join " . TeacherTagsModel::$table . " as tag on tag.id = tl.tag_id " .
            " left join " . AppModel::$table . " as app on app.id = ta.app_id " .
            " left join " . TeacherCollegeModel::$table . " as co on t.college_id = co.id " .
            " left join " . TeacherMajorModel::$table . " as m on t.major_id = m.id ";

        list($where, $map) = self::whereForEntry($params);

        $groupby = " group by t.id ";
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

        $sql_list .= $where . $groupby . $order;

        $teachers = self::getRecordBySql($sql_list, $map);
        return $teachers;
    }

    /**
     * 在职老师搜索条件
     * @param $operator_id
     * @param $params
     * @param $ta_role_id
     * @return array
     */
    public static function whereForEntry($params, $operator_id = '', $ta_role_id = '')
    {
        /** 以下是老师的搜索条件 */
        $where = " where t.status not in (".TeacherModel::ENTRY_REGISTER.", " . TeacherModel::ENTRY_WAIT . ", ".TeacherModel::ENTRY_NO.") ";
        $map = [];
        // 姓名/手机号/ID
        if (!empty($params['name_id'])) {
            $where .= " AND (t.id like :name_id or t.name like :name_id or mobile like :name_id) ";
            $map[':name_id'] = "%".$params['name_id']."%";
        }
        //所属课程
//        if (!empty($params['app_id'])) {
//            $where .= ' AND EXISTS (SELECT * FROM '.TeacherAppExtendModel::$table.' as ta WHERE ta.teacher_id = t.id AND ta.app_id= :app_id and ta.status = '.TeacherAppExtendModel::STATUS_NORMAL.')';
//            $map[':app_id'] = $params['app_id'];
//        }
        //性别
        if (!empty($params['gender'])) {
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
//        // 星级
//        if (!empty($params['evaluate_level'])) {
//            $where .= ' AND EXISTS (SELECT * FROM '.TeacherAppExtendModel::$table.' as ta WHERE ta.teacher_id = t.id AND ta.evaluate_level= :evaluate_level and ta.status = '.TeacherAppExtendModel::STATUS_NORMAL.')';
//            $map[':evaluate_level'] = $params['evaluate_level'];
//        }
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
//        // 教管
//        if (!empty($params['employee_id'])) {
//            $where .= ' AND EXISTS (SELECT 1 FROM '.TeacherAppExtendModel::$table.' as ta WHERE ta.teacher_id = t.id AND ta.employee_id= :employee_id and ta.status = '.TeacherAppExtendModel::STATUS_NORMAL.')';
//            $map[':employee_id'] = $params['employee_id'];
//        }
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
//        //标签ID
//        if (!empty($params['tag_ids']) && is_array($params['tag_ids'])){
//            $tag_ids = $params['tag_ids'];
//            foreach ($tag_ids as $key => $value){
//                $tag_key = ':tag_id_'.$key;
//                $tag_arr[] = $tag_key;
//                $map[$tag_key] = $value;
//                $map[':tag_id_count'] = count($tag_ids);
//            }
//            $where .= 'AND EXISTS (SELECT count(distinct tag_id) n FROM '.TeacherTagRelationModel::$table.' as tr WHERE tr.teacher_id = t.id  AND tr.tag_id in('.implode(",", $tag_arr).') group by teacher_id having n = :tag_id_count) ';
//        }
        //无标签筛选
//        $params['no_tags'] = $params['no_tags'] ?? 0;
//        if ($params['no_tags'] == 1){
//            $where .= ' AND NOT EXISTS (SELECT 1 FROM '.TeacherTagRelationModel::$table.' as tr WHERE tr.teacher_id = t.id) ';
//        }
//        if (!empty($params['ts_course_type'])){
//            $where .= ' AND EXISTS (SELECT 1 FROM '.TeacherAppExtendModel::$table.' as ta WHERE ta.teacher_id = t.id AND ta.ts_course_type= :ts_course_type and ta.status = '.TeacherAppExtendModel::STATUS_NORMAL.')';
//            $map[':ts_course_type'] = $params['ts_course_type'];
//        }
//        //教管权限数据过滤
//        if (!empty($operator_id)){
//            $operator_info = EmployeeModel::getById($operator_id);
//            if (!empty($ta_role_id) && $operator_info['role_id'] == $ta_role_id){
//                $where .= ' AND EXISTS (SELECT 1 FROM '.TeacherAppExtendModel::$table.' as ta WHERE ta.teacher_id = t.id AND ta.employee_id= '.$operator_id.' and ta.status = '.TeacherAppExtendModel::STATUS_NORMAL.')';
//            }
//        }
        return [$where, $map];
    }

    /**
     * 根据app_id获取老师id
     * @param $app_id
     * @return array
     */
    public static function getTeacherIDsByAppID($app_id)
    {
        $teacher_ids = [];
        $result = TeacherAppExtendModel::getTeacherIDsByAppID($app_id, 1);
        foreach ($result as $value) {
            $teacher_ids[] = $value['teacher_id'];
        }
        return $teacher_ids;
    }

    /**
     * 根据老师状态获取老师列表
     * 注册老师列表，只包含注册老师
     * 待入职老师列表，需包含，待入职和不入职两个状态
     * @param $page
     * @param $count
     * @param $params
     * @param $status
     * @return array
     */
    public static function getTeacherByStatus($page, $count, $params, $status)
    {
        $map = [];
        $sql_list = "select t.*," .
            " co.college_name as college_name, ".
            " m.major_name as major_name from " . TeacherModel::$table . " as t " .
            " left join " . TeacherCollegeModel::$table . " as co on t.college_id = co.id ".
            " left join " . TeacherMajorModel::$table . " as m on t.major_id = m.id ";
        $sql_count = "select count(t.id) as totalCount from " . TeacherModel::$table . " as t ";

        /** 以下是老师的搜索条件 */
        // 待入职老师固定参数
        $where = " where t.status in (".$status.") ";

        // 姓名/手机号/ID
        if (!empty($params['name_id'])) {
            $where .= " AND (t.id like :name_id or t.name like :name_id or mobile like :name_id) ";
            $map[':name_id'] = "%".$params['name_id']."%";
        }
        // 渠道来源
        if (!empty($params['channel_id'])) {
            $where .= " AND t.channel_id = :channel_id ";
            $map[':channel_id'] = $params['channel_id'];
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
        // 演奏水平
        if (!empty($params['music_level'])) {
            $where .= " AND t.music_level = :music_level ";
            $map[':music_level'] = $params['music_level'];
        }
        // 老师状态
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
            $where .= " AND t.create_time <= :create_time_end ";
            $map[':create_time_end'] = strtotime($params['create_time_end']);
        }
        //判断是否曾导出是否填写
        if (isset($params['is_export']) && $params['is_export'] != ''){
            $where .= " AND t.is_export = :is_export ";
            $map[':is_export'] = $params['is_export'];
        }
        //标签ID
        if (!empty($params['tag_ids']) && is_array($params['tag_ids'])){
            $tag_ids = $params['tag_ids'];
            foreach ($tag_ids as $key => $value){
                $tag_key = ':tag_id_'.$key;
                $tag_arr[] = $tag_key;
                $map[$tag_key] = $value;
                $map[':tag_id_count'] = count($tag_ids);
            }
            $where .= ' AND EXISTS (SELECT count(distinct tag_id) n FROM erp_teacher_tag_relation as tr WHERE tr.teacher_id = t.id  AND tr.tag_id in('.implode(",", $tag_arr).') group by teacher_id having n = :tag_id_count) ';
        }

        $params['sort'] = empty($params['sort']) ? "DESC" : strtoupper($params['sort']);
        $order = " order by t.create_time " . $params['sort'];
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
     * 注册老师列表
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getRegisterTeacher($params, $page, $count)
    {
        $map = [];

        /** 以下是老师的搜索条件 */
        // 待入职老师固定参数
        $where = " where (tr.referee_type = ".TeacherRefereeModel::REFEREE_TEACHER." or tr.referee_type is null) and tbi.status = ".TeacherModel::ENTRY_REGISTER." ";

        // 老师姓名
        if (!empty($params['name'])) {
            $where .= " AND tbi.name like :name ";
            $map[':name'] = '%'.$params['name'].'%';
        }

        // 手机号
        if (!empty($params['mobile'])) {
            $where .= " AND tbi.mobile like :mobile ";
            $map[':mobile'] = '%'.$params['mobile'].'%';
        }

        // 注册开始时间
        if (!empty($params['create_time_start'])) {
            $where .= " AND tbi.create_time >= :create_time_start ";
            $map[':create_time_start'] = strtotime($params['create_time_start']);
        }

        // 注册结束时间
        if (!empty($params['create_time_end'])) {
            $where .= " AND tbi.create_time <= :create_time_end ";
            $map[':create_time_end'] = strtotime($params['create_time_end']);
        }

        // 渠道来源
        if (!empty($params['channel_id'])) {
            $where .= " AND tbi.channel_id = :channel_id ";
            $map[':channel_id'] = $params['channel_id'];
        }

        //判断是否曾导出是否填写
        if (isset($params['is_export']) && $params['is_export'] != ''){
            $where .= " AND tbi.is_export = :is_export ";
            $map[':is_export'] = $params['is_export'];
        }

        //判断推荐人姓名和手机号是否为空
        if (!empty($params['referee_name']) || !empty($params['referee_mobile'])){
            $where_in = "";
            if (!empty($params['referee_name'])){
                $where_in .= " AND tbi2.name = :referee_name ";
                $map[':referee_name'] = $params['referee_name'];
            }
            if (!empty($params['referee_mobile'])){
                $where_in .= " AND tbi2.mobile = :referee_mobile ";
                $map[':referee_mobile'] = $params['referee_mobile'];
            }
            $where .= " AND tr.referee_id in (select id from ".TeacherModel::$table." as tbi2 where 1=1 ".$where_in." ) ";
        }

        $select_sql = "select tbi.*, tr.referee_id as referee_id, tc.college_name as college_name, tm.major_name as major_name ";
        $count_sql = "select count(1) as count ";

        $sql = " from ".TeacherModel::$table." as tbi
                    left join ".TeacherRefereeModel::$table." as tr on tbi.id = tr.teacher_id
                    left join ".TeacherAppExtendModel::$table." as ta on tbi.id = ta.teacher_id and ta.app_id = tr.app_id
                    left join ".TeacherCollegeModel::$table." as tc on tbi.college_id = tc.id
                    left join ".TeacherMajorModel::$table." as tm on tbi.major_id = tm.id ";

        $params['sort'] = empty($params['sort']) ? "DESC" : strtoupper($params['sort']);
        $order = " order by tbi.create_time " . $params['sort'];
        $limit = " limit " . ($page - 1) * $count . ", " . $count;

        $sql_list = $select_sql . $sql . $where . $order . $limit;
        $sql_count = $count_sql . $sql . $where;

        $totalCount = self::getRecordCountBySql($sql_count, $map);
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }
        $teachers = self::getRecordBySql($sql_list, $map);
        return [$teachers, $totalCount];
    }

    /**
     * 注册老师导出
     * @param $params
     * @return array|null
     */
    public static function exportRegisterTeacher($params)
    {
        $map = [];

        /** 以下是老师的搜索条件 */
        // 待入职老师固定参数
        $where = " where (tr.referee_type = ".TeacherRefereeModel::REFEREE_TEACHER." or tr.referee_type is null) and tbi.status = ".TeacherModel::ENTRY_REGISTER." ";

        // 老师姓名
        if (!empty($params['name'])) {
            $where .= " AND tbi.name like :name ";
            $map[':name'] = '%'.$params['name'].'%';
        }

        // 手机号
        if (!empty($params['mobile'])) {
            $where .= " AND tbi.mobile like :mobile ";
            $map[':mobile'] = '%'.$params['mobile'].'%';
        }

        // 注册开始时间
        if (!empty($params['start_time'])) {
            $where .= " AND tbi.create_time >= :start_time ";
            $map[':start_time'] = strtotime($params['start_time']);
        }

        // 注册结束时间
        if (!empty($params['end_time'])) {
            $where .= " AND tbi.create_time <= :end_time ";
            $map[':end_time'] = strtotime($params['end_time']);
        }

        // 渠道来源
        if (!empty($params['channel_id'])) {
            $where .= " AND tbi.channel_id = :channel_id ";
            $map[':channel_id'] = $params['channel_id'];
        }

        //判断是否曾导出是否填写
        if (isset($params['is_export']) && $params['is_export'] != ''){
            $where .= " AND tbi.is_export = :is_export ";
            $map[':is_export'] = $params['is_export'];
        }

        //判断推荐人姓名和手机号是否为空
        if (!empty($params['referee_name']) || !empty($params['referee_mobile'])){
            $where_in = "";
            if (!empty($params['referee_name'])){
                $where_in .= " AND tbi2.name = :referee_name ";
                $map[':referee_name'] = $params['referee_name'];
            }
            if (!empty($params['referee_mobile'])){
                $where_in .= " AND tbi2.mobile = :referee_mobile ";
                $map[':referee_mobile'] = $params['referee_mobile'];
            }
            $where .= " AND tr.referee_id in (select id from ".TeacherModel::$table." as tbi2 where 1=1 ".$where_in." ) ";
        }

        $select_sql = "select tbi.*, tr.referee_id as referee_id, tc.college_name as college_name, tm.major_name as major_name ";

        $sql = " from ".TeacherModel::$table." as tbi
                    left join ".TeacherRefereeModel::$table." as tr on tbi.id = tr.teacher_id
                    left join ".TeacherAppExtendModel::$table." as ta on tbi.id = ta.teacher_id and ta.app_id = tr.app_id
                    left join ".TeacherCollegeModel::$table." as tc on tbi.college_id = tc.id
                    left join ".TeacherMajorModel::$table." as tm on tbi.major_id = tm.id ";

        $params['sort'] = empty($params['sort']) ? "DESC" : strtoupper($params['sort']);
        $order = " order by tbi.create_time " . $params['sort'];

        $sql_list = $select_sql . $sql . $where . $order;
        $teachers = self::getRecordBySql($sql_list, $map);
        return $teachers;
    }

    /**
     * 预约课程查询老师列表
     * @param $keyword
     * @param $gender
     * @param $collegeId
     * @param $majorId
     * @param $tagIds
     * @param $startTime   int  开始时间戳
     * @param $countTs     int  所需要时间片数量
     * @param $page
     * @param $count
     * @param $appId
     * @param $studentId
     * @param $courseType
     * @return array array[0] 老师列表 array[1] 老师总数
     * @throws \Exception
     */
    public static function searchAvailableForSchedule($keyword, $gender, $collegeId, $majorId, $tagIds,
                                                      $startTime, $countTs, $page, $count, $appId, $studentId, $courseType)
    {
        $studentLevel = StudentAppModel::getLevel($studentId, $appId);
        $where = " t.status = " . self::ENTRY_ON;
        $map = [];
        $join = "";

        if (!empty($keyword)) {
            $where .= " AND (t.id like :keyword or t.name like :keyword or mobile like :keyword) ";
            $map[':keyword'] = '%' . $keyword . '%';
        }

        if (!empty($gender)) {
            $where .= " AND t.gender = :gender ";
            $map[':gender'] = $gender;
        }

        if (!empty($collegeId)) {
            $where .= " AND t.college_id = :college_id ";
            $map[':college_id'] = $collegeId;
        }

        if (!empty($majorId)) {
            $where .= " AND t.major_id = :major_id ";
            $map[':major_id'] = $majorId;
        }

        if (!empty($tagIds) && is_array($tagIds)) {
            $tagArr = [];
            foreach($tagIds as $key => $tagId) {
                $tagMap = ":tag_id_{$key}";
                $map[$tagMap] = $tagId;
                $tagArr[] = $tagMap;
            }
            $map[':tag_id_count'] = count($tagIds);
            $where .= " AND EXISTS (SELECT count(distinct tag_id) n FROM " . TeacherTagRelationModel::$table . " as t_tag WHERE t_tag.teacher_id = t.id  AND t_tag.tag_id in (" . implode(',', $tagArr) . ") group by teacher_id having n = :tag_id_count) ";
        }

        $tsCourseTypes = DictModel::getKeyValue(Constants::DICT_TYPE_COURSE_TYPE_CONV, $courseType);
        if (empty($tsCourseTypes)){
            // 该课程类型不能消耗老师时间片
            SimpleLogger::warning("该课程类型不能使用老师时间片或Dict表中未定义转换规则", [$courseType]);
            throw new \Exception("error_course_type_for_timeslice");
        }

        $teacher_schedule = TeacherScheduleModel::$table;
        $join .= "INNER JOIN (select s.teacher_id, s.app_id, s.s_time FROM {$teacher_schedule} s
                          INNER JOIN {$teacher_schedule} s2 on s.teacher_id = s2.teacher_id
                          and s2.app_id = :app_id
                          and s2.status = :status
                          and s2.is_lock = :lock_status
                          and s2.s_time >= s.s_time and s2.s_time < s.s_time + :duration
                          and s2.ts_course_type in (" . $tsCourseTypes . ")
                          WHERE s.status = :status AND s.is_lock = :lock_status AND s.app_id = :app_id
                          AND s.s_time = :s_time AND s.ts_course_type in (" . $tsCourseTypes . ")
                          GROUP BY s.teacher_id, s.s_time HAVING count(1) = :count_slices ) ts
                          on ts.teacher_id = t.id AND ts.app_id = :app_id AND ts.s_time = :s_time";

        $map[':app_id'] = $appId;
        $map[':status'] = TeacherScheduleModel::TS_STATUS_NORMAL;
        $map[':lock_status'] = TeacherScheduleModel::TS_UNLOCK;
        $map[':duration'] = TeacherScheduleModel::TS_UNIT * $countTs;
        $map[':count_slices'] = $countTs;
        $map[':s_time'] = $startTime;

        $db = MysqlDB::getDB();
        $queryCount = "SELECT count(*) count FROM ". self::$table . " t " . $join . " WHERE " . $where;
        $totalCount = $db->queryAll($queryCount, $map);
        $totalCount = $totalCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }

        $startDate = date('Ymd', $startTime);
        $join .= " LEFT JOIN " . TeacherCollegeModel::$table . " co on t.college_id = co.id
                  LEFT JOIN " . TeacherMajorModel::$table . " m on t.major_id = m.id
                  LEFT JOIN " . TeacherTagRelationModel::$table . " t_tags ON t.id = t_tags.teacher_id
                  LEFT JOIN " . TeacherTagsModel::$table . " tags ON t_tags.tag_id = tags.id
                  LEFT JOIN " . TeacherRecommendModel::$table . " r ON t.id = r.teacher_id AND r.type = " . TeacherRecommendModel::TYPE_NORMAL . " AND r.date = " . $startDate . "
                  LEFT JOIN " . StudentLikeTeacherModel::$table . " slt on slt.teacher_id = t.id and slt.student_id = " . $studentId . " and slt.status = 1";

        $select = "t.id, t.name, t.mobile, t.gender, t.college_id, t.major_id, t.level, t.type,
    co.college_name, m.major_name, if(slt.id is null, 0, 1) AS liked_id, GROUP_CONCAT(distinct tags.name) tags";
        $order = "ORDER BY liked_id DESC, ";
        if (!empty($studentLevel)) {
            $select .= ", CASE WHEN t.level = :student_level THEN 10
                             WHEN t.level > :student_level THEN (20 + t.level - :student_level)
                             WHEN t.level < :student_level THEN (30 + :student_level - t.level)
                        END as level_sort";
            $map[':student_level'] = $studentLevel;
            $order .= "level_sort ASC, ";
        }
        $order .= "r.weight_score DESC ";
        $limit = " LIMIT " . ($page - 1) * $count . "," . $count;
        $query = "
SELECT {$select}
FROM
    " . self::$table . " t " . $join . "
WHERE " . $where . " GROUP BY t.id " . $order . $limit;
        $teachers = $db->queryAll($query, $map);

        return [$teachers, $totalCount];
    }


    /**
     * 模糊搜索老师姓名、电话、id
     * @param $keyword
     * @return array
     */
    public static function searchByKeyword($keyword)
    {
        return MysqlDB::getDB()->select(self::$table, [
            'id', 'name', 'mobile'
        ], [
            'OR' => [
                'id' => $keyword,
                'name[~]' => Util::sqlLike($keyword),
                'mobile[~]' => Util::sqlLike($keyword)
            ]
        ]);
    }

    /**
     * 获取在职教师
     * @return array
     */
    public static function getOnJobTeachers()
    {
        return MysqlDB::getDB()->select(self::$table, ['id', 'name', 'level', 'type'], ['status' => self::ENTRY_ON]);
    }

    /**
     * 查找在手机号在指定数组内的老师
     * @param $mobiles
     * @return array
     */
    public static function selectInMobiles($mobiles) {
        return MysqlDB::getDB()->select(self::$table,'*',['mobile' => $mobiles]);
    }

    /**
     * 更新一个老师的信息
     * @param $id
     * @param $data
     * @return int|null
     */
    public static function updateTeacherBaseInfo($id,$data){
        return self::updateRecord($id, $data);
    }

	/**
	 * 通过教师id获取老师信息
	 * @param  array $teacher_ids
	 * @param  mixed $fields
	 * @return array
	 */
	public static function getTeacherByIds($teacher_ids, $fields = '*') {
		return MysqlDB::getDB()->select(self::$table, $fields, ['id' => $teacher_ids]);
	}

	/**
	 * 排除指定的老师，及其他过滤条件，获取老师数据
	 * @param  array  $exclude 要排队的老师id数组
	 * @param  mixed  $fields
	 * @param  array  $where   要过滤的条件
	 * @param  integer $page
	 * @param  integer $limit
	 * @return array
	 */
	public static function getTeachersByExclude($exclude, $fields = '*', $where, $page = 1, $limit = 20) {

		if($exclude){
            $where['id[!]'] = $exclude;  
        }
		$where['LIMIT'] = [($page - 1) * $limit, $limit];
		return MysqlDB::getDB()->select(self::$table, $fields, $where);
	}

	/**
	 * 根据条件获取总数
	 * @param  array  $where
	 * @return int
	 */
	public static function count($where = []) {

		return MysqlDB::getDB()->count(self::$table, $where);
	}

    /**
     * 根据条件获取可以进行邀约的老师信息
     * @param  array  $exclude_teachers 要排队的老师id
     * @param  int  $course_type        课程类型
     * @param  int  $app_id
     * @param  int  $level            
     * @param  int  $gender           
     * @param  string  $name             
     * @param  int  $teacherType
     * @param  integer $page
     * @param  integer $limit
     * @return array
     */
    public static function getScheduleRequestTeachers($exclude_teachers, $course_type, $app_id, $level = null, $gender = null, $name = null, $teacherType = null, $page = 1, $limit = 20){

        $join=[
            '[><]' . TeacherRecommendModel::$table . "(tr)" => ['t.id' => 'teacher_id'],
            '[><]' . TeacherAppExtendModel::$table . "(ta)" => ['t.id' => 'teacher_id']
        ];
        $where['AND']['t.status'] = self::ENTRY_ON;
        $where['AND']['ta.app_id'] = $app_id;

        if(!empty($exclude_teachers)){
            $where['AND']['t.id[!]'] = $exclude_teachers;
        }

        if(!empty($level)){
            $where['AND']['t.level'] = (int)$level;
        }

        if(!empty($gender)){
            $where['AND']['t.gender'] = (int)$gender;
        }

        if(!empty($name)){
            $where['AND']['t.name[~]'] = Util::sqlLike($name);
        }

        if(!empty($teacherType)){
            $where['AND']['t.type'] = $teacherType;
        }

        //课程类型条件处理
        if($course_type == self::COURSE_EXPERIENCE_AND_FORMAL){
            $where['AND']['ta.ts_course_type'] = $course_type;
        }else{
            $course_type_like = str_replace('0', '_', $course_type);
            $where['AND']['ta.ts_course_type[~]'] = $course_type_like;
        }

        $where['AND']['tr.date'] = date('Ymd');
        //设置推荐类型值
        $filter_course_type = $course_type == TeacherModel::COURSE_EXPERIENCE_CODE ? TeacherRecommendModel::TYPE_EXPERIENCE : TeacherRecommendModel::TYPE_NORMAL ;
        $where['AND']['tr.type'] = $filter_course_type;
        $where['ORDER']['tr.weight_score'] = 'DESC';
        $where['LIMIT'] = [($page-1)*$limit, $limit];

        $res = MysqlDB::getDB()->select(self::$table . "(t)", $join, [
            't.id',
            't.name',
            't.gender',
            't.type',
            'tr.weight_score',
            'tr.data',
        ], $where);

        foreach ($res as &$teacher) {
            $teacher['gender'] = (int) $teacher['gender'];
            $teacher['weight'] = (int) $teacher['weight_score'];
            if(empty($teacher['data'])){
                $teacher['score'] = [];
            }else{
                $teacher['score'] = json_decode($teacher['data'], true);
            }
            unset($teacher['data']);
            unset($teacher['weight_score']);
        }
        
        return $res;
    }

    /**
     * 根据条件获取可以进行邀约的老师数量
     * @param  array  $exclude_teachers 要排队的老师id
     * @param  int  $course_type        课程类型
     * @param  int  $app_id
     * @param  int  $level            
     * @param  int  $gender           
     * @param  string  $name             
     * @param  int  $teacherType
     * @return number
     */
    public static function countScheduleRequestTeachers($exclude_teachers, $course_type, $app_id, $level = null, $gender = null, $name = null, $teacherType = null)
    {
        $join=[
            '[><]' . TeacherRecommendModel::$table . "(tr)" => ['t.id' => 'teacher_id'],
            '[><]' . TeacherAppExtendModel::$table . "(ta)" => ['t.id' => 'teacher_id']
        ];
        $where['AND']['t.status'] = self::ENTRY_ON;
        $where['AND']['ta.app_id'] = $app_id;

        if(!empty($exclude_teachers)){
            $where['AND']['t.id[!]'] = $exclude_teachers;
        }

        if(!empty($level)){
            $where['AND']['t.level'] = (int)$level;
        }

        if(!empty($gender)){
            $where['AND']['t.gender'] = (int)$gender;
        }

        if(!empty($name)){
            $where['AND']['t.name[~]'] = Util::sqlLike($name);
        }

        if(!empty($teacherType)){
            $where['AND']['t.type'] = $teacherType;
        }

        //课程类型条件处理
        if($course_type == self::COURSE_EXPERIENCE_AND_FORMAL){
            $where['AND']['ta.ts_course_type'] = $course_type;
        }else{
            $course_type = str_replace('0', '_', $course_type);
            $where['AND']['ta.ts_course_type[~]'] = $course_type;
        }

        $where['AND']['tr.date'] = date('Ymd');
        //设置推荐类型值
        $filter_course_type = $course_type == TeacherModel::COURSE_EXPERIENCE_CODE ? TeacherRecommendModel::TYPE_EXPERIENCE : TeacherRecommendModel::TYPE_NORMAL ;
        $where['AND']['tr.type'] = $filter_course_type;

        return MysqlDB::getDB()->count(self::$table . "(t)", $join, ['t.id'], $where);
    }

    /**
     * 校验发送邀约的老师是否能选择该课程类型
     * @param  array $teachers    
     * @param  int $course_type 
     * @return boolean
     */
    public static function checkTeachersPermission($teachers, $course_type)
    {
        if($course_type != self::COURSE_TYPE_NONE){
            $course_type = str_replace('0', '_', $course_type);
        }

        $join=['[><]' . TeacherAppExtendModel::$table . "(ta)"  => ['t.id' => 'teacher_id']];

        $where['AND'] = ['t.id' => $teachers, 'ta.ts_course_type[~]' => $course_type];

        $count = MysqlDB::getDB()->count(self::$table . "(t)", $join, ['t.id'], $where);

        return count($teachers) == $count;
    }

    /**
     * 预约体验课查询老师列表
     * @param $tagIds
     * @param $startTime   int  开始时间戳
     * @param $endTime     int  结束时间戳
     * @param $appId
     * @param $studentLevel
     * @param $gender int 性别
     * @return array array[0] 老师列表 array[1] 老师总数
     * @throws \Exception
     */
    public static function crmGetRecommendTeachers($tagIds, $startTime, $endTime, $appId, $studentLevel, $gender)
    {
        $aliasArr = TagAliasRelationModel::getTagsByAlias($tagIds);
        if (empty($aliasArr)) {
            return [];
        }

        $countTs = ceil(($endTime - $startTime) / TeacherScheduleModel::TS_UNIT);

        $where = " t.status = " . self::ENTRY_ON;
        $map = [];
        $join = "";

        foreach ($aliasArr as $alias) {
            $tagArr = explode(',', $alias['tag_ids']);
            $where .= " AND EXISTS (SELECT * FROM " . TeacherTagRelationModel::$table . " as t_tag WHERE t_tag.teacher_id = t.id AND t_tag.tag_id in (" . implode(',', $tagArr) . "))";
        }

        if (!empty($gender)) {
            $where .= " AND t.gender = :gender ";
            $map[':gender'] = $gender;
        }

        // 已确认等级
        $where .= " AND t.level = :student_level ";
        $map[':student_level'] = $studentLevel;

        $tsCourseTypes = DictModel::getKeyValue(Constants::DICT_TYPE_COURSE_TYPE_CONV, CourseModel::TYPE_TEST);
        if (empty($tsCourseTypes)) {
            // 该课程类型不能消耗老师时间片
            SimpleLogger::warning("该课程类型不能使用老师时间片或Dict表中未定义转换规则", [CourseModel::TYPE_TEST]);
            throw new \Exception("error_course_type_for_timeslice");
        }

        $teacher_schedule = TeacherScheduleModel::$table;
        $join .= "INNER JOIN (select s.teacher_id, s.app_id, s.s_time FROM {$teacher_schedule} s
                          INNER JOIN {$teacher_schedule} s2 on s.teacher_id = s2.teacher_id
                          and s2.app_id = :app_id
                          and s2.status = :status
                          and s2.is_lock = :lock_status
                          and s2.s_time >= s.s_time and s2.s_time < :e_time
                          and s2.ts_course_type in (" . $tsCourseTypes . ")
                          WHERE s.status = :status AND s.is_lock = :lock_status AND s.app_id = :app_id
                          AND s.s_time = :s_time AND s.ts_course_type in (" . $tsCourseTypes . ")
                          GROUP BY s.teacher_id, s.s_time HAVING count(1) = :count_slices ) ts
                          on ts.teacher_id = t.id AND ts.app_id = :app_id AND ts.s_time = :s_time";

        $map[':app_id'] = $appId;
        $map[':status'] = TeacherScheduleModel::TS_STATUS_NORMAL;
        $map[':lock_status'] = TeacherScheduleModel::TS_UNLOCK;
        $map[':e_time'] = $endTime;
        $map[':count_slices'] = $countTs;
        $map[':s_time'] = $startTime;


        $startDate = date('Ymd', $startTime);
        $join .= " LEFT JOIN " . TeacherRecommendModel::$table . " r ON t.id = r.teacher_id AND r.type = " . TeacherRecommendModel::TYPE_EXPERIENCE . " AND r.date = " . $startDate;
        $order = " ORDER BY r.weight_score DESC ";

        $query = "
SELECT
    t.id, t.name, t.gender, t.level
FROM
    " . self::$table . " t " . $join . "
WHERE " . $where . $order . "LIMIT 3";

        $db = MysqlDB::getDB();
        $teachers = $db->queryAll($query, $map);

        return $teachers;
    }

    /**
     * 获取注册老师详情
     * @param $teacher_id
     * @return array|null
     */
    public static function getTeacherRegisterDetailById($teacher_id)
    {
        $sql = 'select tbi.*, tr.referee_id as referee_id, tc.college_name as college_name, tm.major_name as major_name, tcl.name as channel_name  
                from '.TeacherModel::$table.' as tbi
                    left join '.TeacherRefereeModel::$table.' as tr on tbi.id = tr.teacher_id
                    left join '.TeacherAppExtendModel::$table.' as ta on tbi.id = ta.teacher_id and ta.app_id = tr.app_id
                    left join '.TeacherCollegeModel::$table.' as tc on tbi.college_id = tc.id
                    left join '.TeacherChannelModel::$table.' as tcl on tbi.channel_id = tcl.id
                    left join '.TeacherMajorModel::$table.' as tm on tbi.major_id = tm.id  
                where (tr.referee_type = '.TeacherRefereeModel::REFEREE_TEACHER.' or tr.referee_type is null) and tbi.status = '.TeacherModel::ENTRY_REGISTER.' and tbi.id = '.$teacher_id;

        $db = MysqlDB::getDB();
        $result = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * 删除未入职老师数据
     * (物理删除, 慎用！！！！)
     * @param $teacher_id
     * @return int|null
     */
    public static function deleteNotEntryTeacher($teacher_id){

        self::delCache($teacher_id);
        $db = MysqlDB::getDB();
        $result = $db->deleteGetCount(self::$table, [
            'id' => $teacher_id,
            'status' => [self::ENTRY_REGISTER, self::ENTRY_WAIT, self::ENTRY_NO]
        ]);
        return $result;
    }

    /**
     * 更新老师最后上课时间
     * @param $teacherId
     * @param $time
     * @return int|null
     */
    public static function updateLastClassTime($teacherId, $time)
    {
        return MysqlDB::getDB()->updateGetCount(self::$table, [
            'last_class_time' => $time
        ], [
            'id' => $teacherId
        ]);
    }
}