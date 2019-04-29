<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:04
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\Util;

class ScheduleModel extends Model
{
    public static $table = "schedule";
    /** 课程状态 */
    const STATUS_BOOK = 1;            //预约成功
    const STATUS_IN_CLASS = 2;        //上课中
    const STATUS_FINISH = -1;         //下课
    const STATUS_CANCEL = -2;         //课程取消

    const TYPE_EXPERIENCE = 1;
    const TYPE_NORMAL = 2;

    /**
     * @param $schedule
     * @return int|mixed|string|null
     */
    public static function insertSchedule($schedule)
    {
        return self::insertRecord($schedule);
    }

    /**
     * @param $id
     * @param bool $isOrg
     * @return mixed
     */
    public static function getDetail($id, $isOrg = true)
    {
        $where = ['s.id' => $id];
        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];

        return MysqlDB::getDB()->get(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.class_id',
            's.org_id',
            'c.name (course_name)',
            'c.type (course_type)',
            'c.duration',
            'cr.name (classroom_name)',
            'cr.campus_id'
        ], $where);
    }

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @param bool $isOrg
     * @return array
     */
    public static function getList($params, $page = -1, $count = 20, $isOrg = true)
    {
        $db = MysqlDB::getDB();
        $where = [];
        if (!empty($params['classroom_id'])) {
            $where['s.classroom_id'] = $params['classroom_id'];
        }
        if (!empty($params['course_id'])) {
            $where['s.course_id'] = $params['course_id'];
        }
        if (isset($params['status']) && is_numeric($params['status'])) {
            $where['s.status'] = $params['status'];
        }
        if (!empty($params['s_time_start'])) {
            $where['s.start_time[>=]'] = strtotime($params['s_time_start']);
        }
        if (!empty($params['s_time_end'])) {
            $where['s.start_time[<]'] = strtotime($params['s_time_end']);
        }
        if (!empty($params['class_id'])) {
            $where['s.class_id'] = $params['class_id'];
        }

        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $totalCount = 0;
        if ($page != -1) {
            // 获取总数
            $totalCount = $db->count(self::$table . " (s)", "*", $where);
            // 分页设置
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }
        // 排序设置
        $where['ORDER'] = [
            'start_time' => 'DESC'
        ];
        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.org_id',
            's.class_id',
            'c.type (course_type)',
            'c.name (course_name)',
            'cr.campus_id',
            'cr.name (classroom_name)'

        ], $where);
        return array($totalCount, $result);
    }

    /**
     * @param $schedule
     * @return array
     */
    public static function checkSchedule($schedule)
    {
        $db = MysqlDB::getDB();
        $where = [
            's.classroom_id' => $schedule['classroom_id'],
            's.status' => array(self::STATUS_BOOK, self::STATUS_IN_CLASS),
            's.start_time[<]' => $schedule['end_time'],
            's.end_time[>]' => $schedule['start_time'],
            's.org_id' => $schedule['org_id'],
        ];

        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
        ];
        $result = $db->select(self::$table . " (s)", $join, [
            's.id',
            's.course_id',
            's.start_time',
            's.end_time',
            's.classroom_id',
            's.create_time',
            's.status',
            's.org_id',
            'c.type (course_type)',
            'c.name (course_name)',
            'cr.name (classroom_name)',
        ], $where);
        return $result;
    }

    /**
     * @param $schedule
     * @return bool
     */
    public static function modifySchedule($schedule)
    {
        $result = self::updateRecord($schedule['id'], $schedule);
        return ($result && $result > 0);
    }

    public static function modifyScheduleByClassId($data,$where) {
        $result = self::batchUpdateRecord($data,$where);
            return ($result && $result > 0);
    }

    /**
     * 学员上课记录
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function attendRecord($orgId, $page, $count, $params)
    {
        $s   = ScheduleModel::$table;
        $cr  = ClassroomModel::$table;
        $c   = CourseModel::$table;
        $su  = ScheduleUserModel::$table;
        $stu = StudentModel::$table;
        $t   = TeacherModel::$table;
        $e   = EmployeeModel::$table;

        $userRoleStudent = ScheduleUserModel::USER_ROLE_STUDENT;
        $userRoleTeacher = ScheduleUserModel::USER_ROLE_TEACHER;

        $where = ' where s.org_id = :org_id ';
        $map   = [':org_id' => $orgId];

        if(!empty($params['classroom_id'])) {
            $where .= ' and s.classroom_id = :classroom_id';
            $map[':classroom_id'] = $params['classroom_id'];
        }
        if(!empty($params['course_id'])) {
            $where .= ' and s.course_id = :course_id';
            $map[':course_id'] = $params['course_id'];
        }
        if(!empty($params['status'])) {
            $where .= ' and s.status = :status';
            $map[':status'] = $params['status'];
        }
        if(!empty($params['start_time'])) {
            $where .= ' and s.start_time >= :start_time';
            $map[':start_time'] = $params['start_time'];
        }
        if(!empty($params['end_time'])) {
            $where .= ' and s.end_time <= :end_time';
            $map[':end_time'] = $params['end_time'];
        }
        if(!empty($params['role_id'])) {
            $where .= ' and e.role_id = :role_id';
            $map[':role_id'] = $params['role_id'];
        }

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select s.*,
               stu.name student_name,
               stu.id   student_id,
               t.name   teacher_name,
               t.id     teacher_id,
               c.name   course_name,
               cr.name  class_room_name
        from {$s} s
               inner join {$cr} cr on cr.id = s.classroom_id and cr.org_id = s.org_id
               inner join {$c} c on c.id = s.course_id and c.org_id = s.org_id
               left join {$su} su on s.id = su.user_id and su.user_role = {$userRoleStudent}
               left join {$stu} stu on su.user_id = stu.id
               left join {$su} su2 on s.id = su2.schedule_id and su2.user_role = {$userRoleTeacher}
               left join {$t} t on t.id = su2.user_id
               left join {$e} e on stu.cc_id = e.id
        {$where} order by s.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count
        from {$s} s
               inner join {$cr} cr on cr.id = s.classroom_id and cr.org_id = s.org_id
               inner join {$c} c on c.id = s.course_id and c.org_id = s.org_id
               left join {$su} su on s.id = su.user_id and su.user_role = {$userRoleStudent}
               left join {$stu} stu on su.user_id = stu.id
               left join {$e} e on stu.cc_id = e.id
        {$where} order by s.create_time desc {$limit}", $map);

        return [$records, $total[0]['count']];
    }

}