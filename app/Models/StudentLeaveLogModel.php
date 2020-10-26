<?php


namespace App\Models;


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

}