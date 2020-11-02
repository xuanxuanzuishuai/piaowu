<?php


namespace App\Models;


use App\Libs\MysqlDB;

class StudentLeaveLogModel extends Model
{
    public static $table = "student_leave_log";

    //取消请假操作人类型 1课管  2用户 3系统（用户退费）
    const CANCEL_OPERATOR_COURSE = 1;
    const CANCEL_OPERATOR_STUDENT = 2;
    const CANCEL_OPERATOR_SYSTEM = 3;


    //请假状态 1正常 2取消 3结束
    const STUDENT_LEAVE_STATUS_NORMAL = 1;
    const STUDENT_LEAVE_STATUS_CANCEL = 2;
    const STUDENT_LEAVE_STATUS_OVER = 3;


    /**
     * 获取学员请假列表
     * @param $studentId
     * @return array
     */
    public static function getStudentLeaveList($studentId)
    {
        $sql = "SELECT 
    sll.id, sll.student_id, sll.gift_code_id,sll.leave_time, sll.start_leave_time, sll.end_leave_time, sll.leave_days, sll.actual_end_time,
    sll.actual_days, sll.cancel_time, sll.leave_status, sll.cancel_operator_type, e.name leave_operator,
	case
	  when sll.cancel_operator_type = :cancel_operator_course then (select `name` from dss_dev.employee where sll.cancel_operator = id)
      when  sll.cancel_operator_type = :cancel_operator_student then (select `name` from dss_dev.student where sll.cancel_operator = id)
      when  sll.cancel_operator_type = :cancel_operator_system then '系统操作'
      end as cancel_operator_name
FROM
    dss_dev.student_leave_log sll  
    left join dss_dev.employee e on sll.leave_operator = e.id 
    where student_id = :student_id order by sll.id desc";

        $map = [
            ':student_id' => $studentId,
            ':cancel_operator_course' => self::CANCEL_OPERATOR_COURSE,
            ':cancel_operator_student' => self::CANCEL_OPERATOR_STUDENT,
            ':cancel_operator_system' => self::CANCEL_OPERATOR_SYSTEM
        ];
        $result = MysqlDB::getDB()->queryAll($sql, $map);
        return $result ?? [];
    }

    /**
     * APP获取学生请假信息
     * @param $studentId
     * @return array|string[]
     */
    public static function getStudentLeaveInfo($studentId)
    {
        $time = time();
        //学生请假信息
        $studentLeaveInfo = StudentLeaveLogModel::getRecord(['student_id' => $studentId, 'leave_status' => StudentLeaveLogModel::STUDENT_LEAVE_STATUS_NORMAL, 'ORDER' => ['id' => 'DESC']]);
        if (empty($studentLeaveInfo) || $studentLeaveInfo['end_leave_time'] < $time) {
            return ['', ''];
        }
        $leaveStartDate = date('Y-m-d', $studentLeaveInfo['start_leave_time']);
        $leaveEndDate = date('Y-m-d', $studentLeaveInfo['end_leave_time']);

        //请假开始时间大于当前，说明请假未开始，返回开始&结束时间
        if ($leaveStartDate > date('Y-m-d', $time)) {
            return [$leaveStartDate, $leaveEndDate];
        } else {
            //请假开始时间小于等于当前时间，代表请假中
            return ['', $leaveEndDate];
        }
    }

}