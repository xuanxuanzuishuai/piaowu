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
            '[><]' . STClassModel::$table . "(class)" => ['s.class_id' => 'id']
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
            'cr.campus_id',
            'class.class_highest',
            'class.name (class_name)'
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

        $join = [
            '[><]' . CourseModel::$table . " (c)" => ['s.course_id' => 'id'],
            '[><]' . ClassroomModel::$table . " (cr)" => ['s.classroom_id' => 'id'],
            '[><]' . STClassModel::$table . " (cl)" => ['s.class_id' => 'id']
        ];

        // 获取总数
        $totalCount = 0;
        if ($page != -1) {
            $totalCount = $db->count(self::$table . " (s)", $join,'s.id', $where);
            // 分页设置
            $where['LIMIT'] = [($page - 1) * $count, $count];
        }

        // 排序设置
        $where['ORDER'] = ['start_time' => 'DESC'];
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
            'cr.name (classroom_name)',
            's.c_t_id',
            'cl.name (class_name)'
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
            's.status' => self::STATUS_BOOK,
            's.start_time[<]' => $schedule['end_time'],
            's.end_time[>]' => $schedule['start_time'],
            's.org_id' => $schedule['org_id'],
        ];

        if (!empty($schedule['id'])) {
            $where['s.id[!]'] = $schedule['id'];
        }
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
     * 学员上课记录 1对1
     * @param $orgId
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function AIAttendRecord($orgId, $page, $count, $params)
    {
        $s   = ScheduleModel::$table;
        $cr  = ClassroomModel::$table;
        $c   = CourseModel::$table;
        $su  = ScheduleUserModel::$table;
        $stu = StudentModel::$table;
        $t   = TeacherModel::$table;
        $e   = EmployeeModel::$table;
        $se  = ScheduleExtendModel::$table;
        $so  = StudentOrgModel::$table;

        $userRoleStudent = ScheduleUserModel::USER_ROLE_STUDENT;
        $userRoleTeacher = ScheduleUserModel::USER_ROLE_TEACHER;
        $userStatus = ScheduleUserModel::STATUS_NORMAL;

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
        if(!empty($params['cc_id'])) {
            $where .= ' and so.cc_id = :cc_id';
            $map[':cc_id'] = $params['cc_id'];
        }
        if(!empty($params['teacher_name'])) {
            $where .= ' and t.name like :teacher_name ';
            $map[':teacher_name'] = "%{$params['teacher_name']}%";
        }
        if(!empty($params['student_name'])) {
            $where .= ' and stu.name like :student_name ';
            $map[':student_name'] = "%{$params['student_name']}%";
        }
        if(!empty($params['teacher_mobile'])) {
            $where .= ' and t.mobile like :teacher_mobile ';
            $map[':teacher_mobile'] = $params['teacher_mobile'];
        }
        if(!empty($params['student_mobile'])) {
            $where .= ' and stu.mobile like :student_mobile ';
            $map[':student_mobile'] = $params['student_mobile'];
        }

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select s.*,
               stu.name student_name,
               stu.mobile student_mobile,
               stu.id   student_id,
               t.name   teacher_name,
               t.id     teacher_id,
               t.mobile teacher_mobile,
               c.name   course_name,
               cr.name  class_room_name,
               se.opn_lessons,
               se.detail_score,
               se.class_score,
               se.remark
        from {$s} s
               left join {$cr} cr on cr.id = s.classroom_id
               inner join {$c} c on c.id = s.course_id
               left join {$su} su on s.id = su.schedule_id and su.user_role = {$userRoleStudent} and su.status = {$userStatus}
               left join {$stu} stu on su.user_id = stu.id
               left join {$su} su2 on s.id = su2.schedule_id and su2.user_role = {$userRoleTeacher} and su2.status = {$userStatus}
               left join {$t} t on t.id = su2.user_id
               left join {$so} so on so.student_id = stu.id and so.org_id = s.org_id
               left join {$e} e on e.id = so.cc_id
               left join {$se} se on se.schedule_id = s.id
        {$where} order by s.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count
        from {$s} s
               left join {$cr} cr on cr.id = s.classroom_id
               inner join {$c} c on c.id = s.course_id
               left join {$su} su on s.id = su.schedule_id and su.user_role = {$userRoleStudent} and su.status = {$userStatus}
               left join {$stu} stu on su.user_id = stu.id
               left join {$so} so on so.student_id = stu.id and so.org_id = s.org_id
               left join {$e} e on so.cc_id = e.id
               left join {$su} su2 on s.id = su2.schedule_id and su2.user_role = {$userRoleTeacher} and su2.status = {$userStatus}
               left join {$t} t on t.id = su2.user_id
        {$where} order by s.create_time desc", $map);

        return [$records, $total[0]['count']];
    }

    /**
     * 学员上课记录，班级课次
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
        $so  = StudentOrgModel::$table;

        $userRoleStudent = ScheduleUserModel::USER_ROLE_STUDENT;
        $userRoleTeacher = ScheduleUserModel::USER_ROLE_TEACHER;
        $userStatus = ScheduleUserModel::STATUS_NORMAL;

        $where = ' where s.org_id = :org_id ';
        $map   = [':org_id' => $orgId];

        if(!empty($params['classroom_id'])) {
            $where .= ' and s.classroom_id = :classroom_id';
            $map[':classroom_id'] = $params['classroom_id'];
        }
        if(!empty($params['course_id'])) {
            $where .= ' and s.course_id != :course_id';
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
        if(!empty($params['cc_id'])) {
            $where .= ' and so.cc_id = :cc_id';
            $map[':cc_id'] = $params['cc_id'];
        }

        if(!empty($params['student_name'])) {
            $where .= ' and stu.name like :student_name ';
            $map[':student_name'] = "%{$params['student_name']}%";
        }
        if(!empty($params['student_mobile'])) {
            $where .= ' and stu.mobile like :student_mobile ';
            $map[':student_mobile'] = $params['student_mobile'];
        }

        $limit = Util::limitation($page, $count);

        $db = MysqlDB::getDB();

        $records = $db->queryAll("select s.*,
               stu.name student_name,
               stu.id   student_id,
               stu.mobile student_mobile,
               t.name   teacher_name,
               t.id     teacher_id,
               c.name   course_name,
               cr.name  class_room_name
        from {$s} s
               inner join {$cr} cr on cr.id = s.classroom_id
               inner join {$c} c on c.id = s.course_id and c.org_id = s.org_id
               inner join {$su} su on s.id = su.schedule_id and su.user_role = {$userRoleStudent} and su.status = {$userStatus}
               inner join {$stu} stu on su.user_id = stu.id
               left join {$su} su2 on s.id = su2.schedule_id and su2.user_role = {$userRoleTeacher} and su2.status = {$userStatus}
               left join {$t} t on t.id = su2.user_id
               left join {$so} so on so.student_id = stu.id and so.org_id = s.org_id
               left join {$e} e on e.id = so.cc_id
        {$where} order by s.create_time desc {$limit}", $map);

        $total = $db->queryAll("select count(*) count
        from {$s} s
               inner join {$cr} cr on cr.id = s.classroom_id
               inner join {$c} c on c.id = s.course_id and c.org_id = s.org_id
               inner join {$su} su on s.id = su.schedule_id and su.user_role = {$userRoleStudent} and su.status = {$userStatus}
               inner join {$stu} stu on su.user_id = stu.id
               left join {$so} so on so.student_id = stu.id and so.org_id = s.org_id
               left join {$e} e on so.cc_id = e.id
        {$where} order by s.create_time desc", $map);

        return [$records, $total[0]['count']];
    }

    /**
     * 获取课消数据
     * @param $startTime
     * @param $endTime
     * @return array|null
     */
    public static function selectFinishedSchedules($startTime, $endTime)
    {
        // '订单编号', '签约人', '学员', '套餐名称', '签约日期', '课时单价',
        // '上课日期', '上课时间', '上课校区', '上课节数', '消课金额', '操作人', '消课日期', '已消课金额', '剩余课时金额'

        $e = EmployeeModel::$table;
        $db = MysqlDB::getDB();

        $records = $db->queryAll("
SELECT
    s.id schedule_id, s.start_time, s.end_time, c.name course_name, cp.name campus_name,
    su.price, stu.name student_name,
    log.create_time reduce_time, log.balance reduce_num, log.new_balance, e.name operator_name,
    GROUP_CONCAT(b.id ORDER BY b.end_time) bill_ids, SUM(b.amount) total_amount, MIN(b.end_time) bill_time,
    GROUP_CONCAT(be.name ORDER BY b.end_time) bill_operator
FROM
    " . ScheduleModel::$table . " s
        INNER JOIN
    " . CourseModel::$table . " c ON c.id = s.course_id
        INNER JOIN
    " . ClassroomModel::$table . " cr ON cr.id = s.classroom_id
        INNER JOIN
    " . CampusModel::$table . " cp ON cp.id = cr.campus_id
        INNER JOIN
    " . ScheduleUserModel::$table . " su ON su.schedule_id = s.id
        AND su.user_role = " . ScheduleUserModel::USER_ROLE_STUDENT . "
        AND su.status = " . ScheduleUserModel::STATUS_NORMAL . "
        AND su.is_deduct = " . ScheduleUserModel::DEDUCT_STATUS . "
        INNER JOIN
    " . StudentModel::$table . " stu ON stu.id = su.user_id
        LEFT JOIN
    " . StudentAccountLogModel::$table . " log ON log.schedule_id = s.id AND log.type = " . StudentAccountLogModel::TYPE_REDUCE . "
        LEFT JOIN
    {$e} e ON e.id = log.operator_id
        INNER JOIN
    " . StudentAccountModel::$table . " sa ON sa.id = log.s_a_id AND sa.student_id = stu.id
        LEFT JOIN
    " . BillModel::$table . " b ON b.student_id = stu.id
        AND b.org_id = s.org_id
        AND b.pay_status = " . BillModel::PAY_STATUS_PAID . "
        AND b.is_disabled = " . BillModel::NOT_DISABLED . "
        AND b.is_enter_account = " . BillModel::IS_ENTER_ACCOUNT . "
        AND b.add_status = " . BillModel::ADD_STATUS_APPROVED . "
         LEFT JOIN
    {$e} be ON be.id = b.operator_id
WHERE
    s.status = " . ScheduleModel::STATUS_FINISH . "
    AND s.start_time >= " . strtotime($startTime) . "
    AND s.end_time < " . strtotime($endTime) . "
GROUP BY s.id, su.user_id
ORDER BY s.start_time, s.id", []);

        return !empty($records) ? $records : [];
    }

    /**
     * 获取学生课程金额，状态预约成功，且没有扣费
     * @param $studentIds
     * @return array|null
     */
    public static function getTakeUpStudentBalances($studentIds)
    {
        $db = MysqlDB::getDB();

        $records = $db->queryAll("
SELECT
    su.user_id, sum(su.price) price
FROM
    schedule s
        INNER JOIN
    schedule_user su ON su.schedule_id = s.id
        AND su.user_role = " . ScheduleUserModel::USER_ROLE_STUDENT . "
        AND su.user_status != " . ScheduleUserModel::STUDENT_STATUS_LEAVE . "
        AND su.status = " . ScheduleUserModel::STATUS_NORMAL . "
        AND su.is_deduct != " . ScheduleUserModel::DEDUCT_STATUS . "
WHERE
    s.status = " . ScheduleModel::STATUS_BOOK . "
        AND su.user_id IN (" . implode(',', $studentIds) .  ")
GROUP BY su.user_id", []);

        return !empty($records) ? $records : [];
    }
}