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
use App\Services\ChannelService;
use Medoo\Medoo;

class StudentModel extends Model
{
    public static $table = 'student';
    public static $redisExpire = 1;

    const GENDER_UNKNOWN = 0; // 保密
    const GENDER_MALE = 1; //男
    const GENDER_FEMALE = 2; //女

    const STATUS_NORMAL = 1;
    const STATUS_STOP = 0;

    /**
     * 获取客户数据
     * @param $page
     * @param $count
     * @param $params
     * @param $time_now
     * @param $time_thirty_days_ago
     * @return array|null
     */
    public static function fetchStudentData($page, $count, $params, $time_now, $time_thirty_days_ago)
    {
        // 获取学生id
        $studentIds = self::fetchStudentIds($page, $count, $params, $time_now, $time_thirty_days_ago);
        if(empty($studentIds)){
            return [];
        }
        // 获取学生数据
        $students = self::fetchStudents($params, $studentIds, $time_now, $time_thirty_days_ago);
        return $students;
    }

    /**
     * 获取学生数据
     * @param $params
     * @param $ids
     * @param $time_now
     * @param $time_thirty_days_ago
     * @return array|null
     */
    public static function fetchStudents($params, $ids, $time_now, $time_thirty_days_ago)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT s.id,
                       s.name,
                       s.mobile,
                       s.channel_id,
                       sa.app_id,
                       sa.first_pay_time pay_time,
                       sa.level,
                       sa.ca_id,
                       sa.cc_id,
                  0 AS 30_days_expend_class_count,
                  0 AS expend_class_count,
                  0 AS remain_count,
                  0 AS last_book_class_time,
                  0 AS last_in_class_time,
                  0 AS last_remark_time
                FROM '.StudentModel::$table.' s
                INNER JOIN '.StudentAppModel::$table.' sa ON s.id = sa.student_id
                WHERE sa.app_id = :app_id AND s.id IN ('.implode(',', $ids).')
                ORDER BY s.id DESC';
        $paramsMap[':app_id'] = $params['app_id'];
        return $db->queryAll($sql, $paramsMap);
    }

    /**
     * 获取查询学生的id数组
     * @param $page
     * @param $count
     * @param $params
     * @param $time_now
     * @param $time_thirty_days_ago
     * @return array
     */
    public static function fetchStudentIds($page, $count, $params, $time_now, $time_thirty_days_ago)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT es.id
                FROM '.self::$table.' es
                INNER JOIN '.StudentAppModel::$table.' esa ON es.id = esa.student_id';
        list($whereSql, $paramsMap) = self::makeStudentSqlWhere($params, $time_now, $time_thirty_days_ago);
        if($whereSql === false){
            return [];
        }
        $sql .= $whereSql;
        $offset = ($page - 1) * $count;
        $sql .= " ORDER BY es.id DESC LIMIT $offset, $count ";
        $data = $db->queryAll($sql, $paramsMap);
        return array_column($data, 'id');
    }

    /**
     * 获取符合条件客户总数
     * @param $params
     * @param $time_now
     * @param $time_thirty_days_ago
     * @return int
     */
    public static function fetchStudentCount($params, $time_now, $time_thirty_days_ago)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT count(es.id) total
                FROM '.self::$table.' es
                INNER JOIN '.StudentAppModel::$table.' esa ON es.id = esa.student_id';
        list($whereSql, $paramsMap) = self::makeStudentSqlWhere($params, $time_now, $time_thirty_days_ago);
        if($whereSql === false){
            return 0;
        }
        $sql .= $whereSql;
        $sql .= ' ORDER BY es.id DESC ';
        $data = $db->queryAll($sql, $paramsMap);
        return $data[0]['total'];
    }

    /**
     * 生成客户查询条件sql
     * @param $params
     * @param $time_now
     * @param $time_thirty_days_ago
     * @return array
     */
    public static function makeStudentSqlWhere($params, $time_now, $time_thirty_days_ago)
    {
        $sql = '';
        $condition = [];
        if(!self::isParamsEmpty($params)){
            $sql .= ' WHERE esa.app_id = :app_id ';
            $condition[':app_id'] = $params['app_id'];

            if(!empty($params['channel_id'])){
                $sql .= ' and es.channel_id = :channel_id ';
                $condition[':channel_id'] = $params['channel_id'];
            }
            if(empty($params['channel_id']) && !empty($params['parent_channel_id'])){
                $channels = ChannelService::getChannels($params['parent_channel_id']);
                if(empty($channels)){
                    return [false, false];
                }else{
                    $channelIdArr = [];
                    foreach($channels as $channel){
                        $channelIdArr[] = $channel['id'];
                    }
                    $sql .= ' and es.channel_id in ('.implode(',', $channelIdArr).') ';

                }
            }
            if(!empty($params['level']) || $params['level'] === '0'){
                $sql .= ' and esa.level = :level ';
                $condition[':level'] = $params['level'];
            }
            if(!empty($params['name'])){
                $sql .= ' and es.name like :name ';
                $condition[':name'] = '%'.$params['name'].'%';
            }
            if(!empty($params['tel'])){
                $sql .= ' and es.mobile like :tel ';
                $condition[':tel'] = '%'.$params['tel'].'%';
            }
            if(!empty($params['payed_date_start'])){
                $start = strtotime($params['payed_date_start']);
                $sql .= ' and esa.first_pay_time >= :payed_start ';
                $condition[':payed_start'] = $start;
            }
            if(!empty($params['payed_date_end'])){
                $end = strtotime($params['payed_date_end']);
                $sql .= ' and esa.first_pay_time <= :payed_end ';
                $condition[':payed_end'] = $end;
            }
            if(!empty($params['ca_id'])){
                $sql .= ' and esa.ca_id = :ca_id ';
                $condition[':ca_id'] = $params['ca_id'];
            }
            if(!empty($params['student_status'])){
                $sql .= ' and esa.status = :student_status ';
                $condition[':student_status'] = $params['student_status'];
            }
            //学生上课数条件
            if(!empty($params['expend_class_count'])){
                $sql .= ' AND EXISTS
                                (SELECT sum(used_lesson_count+used_free_count+deducted_count) n
                                 FROM '.StudentCourseModel::$table.' sc
                                 INNER JOIN '.CourseModel::$table.' c ON sc.`course_id` = c.id
                                 WHERE sc.student_id = es.id
                                   AND c.`app_id` = :app_id
                                 GROUP BY sc.student_id
                                 HAVING n = :expend_class_count) ';
                $condition[':expend_class_count'] = $params['expend_class_count'];
            }
            // 学生30天耗课数条件
            if(!empty($params['expend_class_start']) || !empty($params['expend_class_end'])){
                $sql .= ' AND EXISTS
                            (SELECT sum(used_count) n
                             FROM '.ScheduleUserModel::$table.' su
                             INNER JOIN '.ScheduleModel::$table.' sch ON sch.id = su.schedule_id
                             WHERE start_time >= '.$time_thirty_days_ago.'
                               AND end_time <= '.$time_now.'
                               AND su.user_role = '.ScheduleUserModel::USER_ROLE_STUDENT.'
                               AND su.user_id = es.id
                             GROUP BY su.user_id
                             HAVING ';
                $i = 0;
                if(!empty($params['expend_class_start'])){
                    $sql .= ' n >= :expend_class_start ';
                    $condition[':expend_class_start'] = $params['expend_class_start'];
                    $i++;
                }
                if(!empty($params['expend_class_end'])){
                    if($i > 0){
                        $sql .= ' AND ';
                    }
                    $sql .= ' n <= :expend_class_end ';
                    $condition[':expend_class_end'] = $params['expend_class_end'];
                }
                $sql .= ' ) ';
            }
            // 学生最后约课时间条件
            if(!empty($params['last_book_date_start']) || !empty($params['last_book_date_end'])){

                $sql .= ' AND EXISTS
                            (SELECT max(sch.create_time) m_t
                             FROM '.ScheduleUserModel::$table.' su
                             INNER JOIN '.ScheduleModel::$table.' sch ON sch.id = su.schedule_id
                             WHERE su.user_role = '.ScheduleUserModel::USER_ROLE_STUDENT.'
                               AND sch.status IN ('.
                                ScheduleModel::STATUS_FINISH.','.
                                ScheduleModel::STATUS_BOOK.','.
                                ScheduleModel::STATUS_IN_CLASS.')
                               AND su.user_id = es.id
                             GROUP BY su.user_id
                             HAVING ';
                $i = 0;
                if(!empty($params['last_book_date_start'])){
                    $start = strtotime($params['last_book_date_start']);
                    $sql .= ' m_t >= :last_book_time_start ';
                    $condition[':last_book_time_start'] = $start;
                    $i++;
                }
                if(!empty($params['last_book_date_end'])){
                    if($i > 0){
                        $sql .= ' AND ';
                    }
                    $end = strtotime($params['last_book_date_end']);
                    $sql .= ' m_t <= :last_book_time_end ';
                    $condition[':last_book_time_end'] = $end;
                }
                $sql .= ' ) ';
            }
            // 学生最后上课时间条件
            if(!empty($params['last_in_class_date_start']) || !empty($params['last_in_class_date_end'])){
                $sql .= ' AND EXISTS
                            (SELECT max(sch.`start_time`) m_t
                             FROM '.ScheduleUserModel::$table.' su
                             INNER JOIN '.ScheduleModel::$table.' sch ON sch.id = su.schedule_id
                             WHERE su.user_role = '.ScheduleUserModel::USER_ROLE_STUDENT.'
                               AND sch.status IN ('.
                                ScheduleModel::STATUS_FINISH.','.
                                ScheduleModel::STATUS_IN_CLASS.')
                               AND su.user_id = es.id
                             GROUP BY su.user_id
                             HAVING ';
                $i = 0;
                if(!empty($params['last_in_class_date_start'])){
                    $start = strtotime($params['last_in_class_date_start']);
                    $sql .= ' m_t >= :last_in_class_time_start ';
                    $condition[':last_in_class_time_start'] = $start;
                    $i++;
                }
                if(!empty($params['last_in_class_date_end'])){
                    if($i > 0){
                        $sql .= ' AND ';
                    }
                    $end = strtotime($params['last_in_class_date_end']);
                    $sql .= ' m_t <= :last_in_class_time_end ';
                    $condition[':last_in_class_time_end'] = $end;
                }
                $sql .= ' ) ';
            }
            // 学生剩余课程条件
            if(!empty($params['remain_class_count_start']) || !empty($params['remain_class_count_end'])){
                $sql .= ' AND EXISTS
                            (SELECT sum(lesson_count+free_count) n
                             FROM '.StudentCourseModel::$table.' sc
                             WHERE sc.student_id = es.id
                             GROUP BY sc.student_id
                             HAVING ';
                $i = 0;
                if(!empty($params['remain_class_count_start'])){
                    $sql .= ' n >=  :remain_class_count_start ';
                    $condition[':remain_class_count_start'] = $params['remain_class_count_start'];
                    $i++;
                }
                if(!empty($params['remain_class_count_end'])){
                    if($i > 0){
                        $sql .= ' AND ';
                    }
                    $sql .= ' n <= :remain_class_count_end';
                    $condition[':remain_class_count_end'] = $params['remain_class_count_end'];
                }
                $sql .= ' ) ';
            }
            // 学生最后跟进时间条件
            if(!empty($params['last_remark_date_start']) || !empty($params['last_remark_date_end'])){
                $sql .= ' AND EXISTS
                            (SELECT max(create_time) m_t
                             FROM '.CommentsModel::$table.'
                             WHERE `student_id` = es.id
                             GROUP BY student_id
                             HAVING ';
                $i = 0;
                if(!empty($params['last_remark_date_start'])){
                    $start = strtotime($params['last_remark_date_start']);
                    $sql .= ' m_t >= :last_remark_time_start ';
                    $condition[':last_remark_time_start'] = $start;
                    $i++;
                }
                if(!empty($params['last_remark_date_end'])){
                    if($i > 0){
                        $sql .= ' AND ';
                    }
                    $end = strtotime($params['last_remark_date_end']);
                    $sql .= ' m_t <= :last_remark_time_end ';
                    $condition[':last_remark_time_end'] = $end;
                }
                $sql .= ' ) ';
            }
        }

        return [$sql, $condition];
    }

    /**
     * 检查是否所有参数为空
     * @param $params
     * @return bool
     */
    public static function isParamsEmpty($params)
    {
        foreach($params as $param){
            if(!empty($param)) return false;
        }
        return true;
    }

    /**
     * 更新学生信息
     * @param $studentId
     * @param $data
     * @return int|null affectRow
     */
    public static function updateStudent($studentId, $data)
    {
        $params = [
            'update_time' => time()
        ];

        if(isset($data['name'])){
            $params['name'] = $data['name'];
        }
        if(isset($data['gender'])){
            $params['gender'] = $data['gender'];
        }
        if(isset($data['birthday'])){
            $params['birthday'] = $data['birthday'];
        }
        return self::updateRecord($studentId, $params,false);
    }

    /**
     * 通过手机号获取用户
     * @param $mobile
     * @return mixed
     */
    public static function getByMobile($mobile)
    {
        return MysqlDB::getDB()->get(self::$table, '*',['mobile' => $mobile]);
    }

    /**
     * 通过uuid获取用户
     * @param $uuid
     * @return mixed
     */
    public static function getByUUID($uuid)
    {
        return MysqlDB::getDB()->get(self::$table, '*', ['uuid' => $uuid]);
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
    public static function saveStudent($params, $uuid,$operatorId = 0 )
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

        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    /**
     * 查找学生
     * @param $keyword
     * @return array
     */
    public static function getStudents($keyword)
    {
        return MysqlDB::getDB()->select(self::$table, '*', [
            'OR' => [
                'id' => $keyword,
                'name[~]' => $keyword,
                'mobile[~]' => $keyword . '%'
            ],
            'LIMIT' => [0, 100]
        ]);
    }

    /**
     * 模糊搜索，按姓名查找学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchByName($keyword,$limit){
        return MysqlDB::getDB()->select(self::$table,['id(student_id)','name','mobile'],['name[~]' => $keyword,'LIMIT' => $limit]);
    }

    /**
     * 模糊搜索，按手机号查找学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchByMobile($keyword,$limit){
        $db = MysqlDB::getDB();
        return $db->select(self::$table,['id(student_id)','name','mobile'],['mobile[~]' => $keyword.'%','LIMIT' => $limit]);
    }

    /**
     * 模糊搜索，按手机号查找付费学生
     * @param $keyword
     * @param $limit
     * @return array
     */
    public static function searchPaidUserByMobile($keyword,$limit)
    {
        $db = MysqlDB::getDB();
        return $db->select(self::$table,
            [
                '[><]' . StudentAppModel::$table => ['id' => 'student_id']
            ],
            [
                'student_id',
                'name',
                'mobile'
            ],
            [
                'AND' => [
                            'mobile[~]' => $keyword,
                            StudentAppModel::$table . '.status' => StudentAppModel::STATUS_PAID
                        ],
                'LIMIT' => $limit
            ]);
    }

    /**
     * 获取学生的国家代码编号
     * @param $studentId
     * @return mixed
     */
    public static function getCountryCode($studentId)
    {
        return MysqlDB::getDB()->get(self::$table, [
            '[>]' . CountryCodeModel::$table => ['country_code' => 'code']
        ], [
            self::$table . '.country_code',
            CountryCodeModel::$table . '.name_cn'
        ], [
            self::$table . '.id' => $studentId
        ]);
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

        $status = TeacherStudentModel::STATUS_NORMAL;

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
        if(!empty($params['is_bind'])) {
            $where .= " and so.status = :is_bind ";
            $map[':is_bind'] = "{$params['is_bind']}";
        }

        if ($orgId > 0) {
            $sql = "select s.*,e.name cc_name, t.teacher_id, te.name teacher_name, t.status ts_status, so.status bind_status
                from {$s} s inner join {$so} so
                on s.id = so.student_id
                and so.org_id = {$orgId} left join {$t} t on s.id = t.student_id and t.status = {$status}
                and t.org_id = {$orgId}
                left join {$te} te on te.id = t.teacher_id
                left join {$e} e on e.id = s.cc_id
                {$where}
                order by s.create_time desc {$limit}";

            $countSql = "select count(*) count from {$s} s inner join {$so} so on s.id = so.student_id
                    and so.org_id = {$orgId} {$where}";
        } else {
            $sql = "select s.*,e.name cc_name, t.teacher_id, te.name teacher_name, t.status ts_status, so.status bind_status
                from {$s} s inner join {$so} so
                on s.id = so.student_id
                left join {$t} t on s.id = t.student_id and t.status = {$status}
                left join {$te} te on te.id = t.teacher_id
                left join {$e} e on e.id = s.cc_id
                {$where}
                order by s.create_time desc {$limit}";

            $countSql = "select count(*) count from {$s} s inner join {$so} so on s.id = so.student_id {$where}";
        }

        $records = $db->queryAll($sql, $map);

        $total = $db->queryAll($countSql, $map);

        return [$records, $total[0]['count']];
    }

    /**
     * 查询一条指定机构和学生id的记录
     * @param $orgId
     * @param $studentId
     * @param $status null表示不限制状态
     * @return array|null
     */
    public static function getOrgStudent($orgId, $studentId, $status = null)
    {
        $db = MysqlDB::getDB();
        $s = StudentModel::$table;
        $so = StudentOrgModel::$table;
        $st = StudentOrgModel::STATUS_NORMAL;

        $sql = "select s.* from {$s} s,{$so} so where s.id = so.student_id
        and so.status = {$st} and so.org_id = {$orgId} and s.id = {$studentId} ";

        if(!is_null($status)) {
            $sql .= " and so.status = {$status} ";
        }

        $records = $db->queryAll($sql);

        return empty($records) ? [] : $records[0];
    }

    /**
     * 模糊查询，根据学生姓名或者手机号
     * 姓名模糊匹配，手机号等于匹配
     * @param $orgId
     * @param $key
     * @return array|null
     */
    public static function fuzzySearch($orgId, $key)
    {
        $db = MysqlDB::getDB();
        $s = StudentModel::$table;
        $so = StudentOrgModel::$table;
        $st = StudentOrgModel::STATUS_NORMAL;

        if(!empty(preg_match('/^1\d{10}$/', $key))) {
            $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so where s.id = so.student_id
        and so.status = {$st} and so.org_id = {$orgId} and s.mobile = {$key}";
        } else {
            $sql = "select s.id,s.name,s.mobile,so.org_id from {$s} s,{$so} so where s.id = so.student_id
        and so.status = {$st} and so.org_id = {$orgId} and s.name like '%{$key}%'";
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
        ],
        [
            'so.org_id' => $orgId,
            's.id'      => $studentIds,
        ]);

        return $records;
    }

    /**
     * 批量更新学生的课管
     * @param $studentIds
     * @param $employeeId
     * @return int|null
     */
    public static function updateStudentCC($studentIds, $employeeId)
    {
        $db = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(self::$table, [
            'cc_id'       => $employeeId,
            'update_time' => time(),
        ], [
            'id' => $studentIds
        ]);
        return $affectRows;
    }
}