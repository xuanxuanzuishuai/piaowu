<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:27
 */

namespace App\Models;


use App\Controllers\Schedule\ScheduleUser;

class ScheduleUserModel extends Model
{
    public static $table = "schedule_user";

    const STATUS_CANCEL = 0; //废除
    const STATUS_NORMAL = 1; //正常

    // 学生子状态
    const STUDENT_STATUS_BOOK = 1;         // 已预约
    const STUDENT_STATUS_CANCEL = 2;       // 已取消 只有体验课有已取消状态
    const STUDENT_STATUS_LEAVE = 3;        // 已请假
    const STUDENT_STATUS_ATTEND = 4;       // 已出席
    const STUDENT_STATUS_NOT_ATTEND = 5;   // 未出席
    // 老师子状态
    const TEACHER_STATUS_SET = 1;          // 已分配
    const TEACHER_STATUS_LEAVE = 2;        // 已请假
    const TEACHER_STATUS_ATTEND = 3;       // 已出席
    const TEACHER_STATUS_NOT_ATTEND = 4;   // 未出席

    /**
     * @param $inserts
     * @return bool
     */
    public static function insertSUs($inserts) {
        return self::batchInsert($inserts,false);
    }

    public static function getSUBySIds($sIds,$status = array(self::STATUS_NORMAL)) {
        $sql = "select su.user_id,su.user_role,su.id,su.st_id,su.create_time,su.status,t.name as teacher_name,s.name as student_name from ".self::$table ." as su "
            ." left join ".StudentModel::$table." as s on su.user_id = s.id and su.user_role = ".ScheduleTaskUserModel::USER_ROLE_S
            ." left join ".TeacherModel::$table." as t on su.user_id = t.id and su.user_role = ".ScheduleTaskUserModel::USER_ROLE_T
            ." where su.schedule_id in (".implode(',',$sIds).") and su.status in (".implode(",",$status).")";

        return MysqlDB::getDB()->queryAll($sql,\PDO::FETCH_COLUMN);
    }
}