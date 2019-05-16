<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:27
 */

namespace App\Models;

use App\Libs\MysqlDB;

class ScheduleUserModel extends Model
{
    public static $table = "schedule_user";

    const STATUS_CANCEL = 0; //废除
    const STATUS_NORMAL = 1; //正常

    const USER_ROLE_STUDENT = 1; //学生
    const USER_ROLE_TEACHER = 2; //老师
    const USER_ROLE_CLASS_TEACHER = 3; // 班主任

    // 学生子状态
    const STUDENT_STATUS_BOOK = 1;         // 已预约
    const STUDENT_STATUS_CANCEL = 2;       // 已取消
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
        return MysqlDB::getDB()->insertGetID(self::$table, $inserts);
    }

    /**
     * @param $sIds
     * @param array $status
     * @return array|null
     */
    public static function getSUBySIds($sIds,$status = array(self::STATUS_NORMAL)) {
        $sql = "select su.user_id, su.user_role, su.id, su.schedule_id, su.create_time, su.status, t.name as teacher_name, 
                case when su.user_role=" . ClassUserModel::USER_ROLE_S . " then (select sum(balance) from " . StudentAccountModel::$table .
                " where student_id=su.user_id) else null end as balance,
                s.name as student_name, su.user_status, su.price from "
            . self::$table . " as su "
            . " left join " . StudentModel::$table." as s on su.user_id = s.id and su.user_role = " . ClassUserModel::USER_ROLE_S
            . " left join " . TeacherModel::$table." as t on su.user_id = t.id and su.user_role in( " . ClassUserModel::USER_ROLE_T."," .ClassUserModel::USER_ROLE_HT.")"
            . " where su.schedule_id in (" . implode(',',$sIds) . ") and su.status in (" . implode(",",$status) . ")";

        return MysqlDB::getDB()->queryAll($sql);
    }

    /**
     * 检查学生、老师时间是否冲突
     * @param $userIds
     * @param $userRole
     * @param $startTime
     * @param $endTime
     * @param $orgSId
     * @param bool $isOrg
     * @return array
     */
    public static function checkScheduleUser($userIds, $userRole, $startTime, $endTime, $orgSId, $isOrg = true)
    {
        $where = [
            'su.user_id' => $userIds,
            'su.user_role' => $userRole,
            's.status' => array(ScheduleModel::STATUS_BOOK,ScheduleModel::STATUS_IN_CLASS),
            's.start_time[<]' => $endTime,
            's.end_time[>]' => $startTime,
            'su.status' => array(self::STATUS_NORMAL),
        ];
        if (!empty($orgSId)) {
            $where['s.id[!]'] = $orgSId;
        }

        if ($isOrg == true) {
            global $orgId;
            if ($orgId > 0)
                $where['s.org_id'] = $orgId;
        }
        $columns = [
            's.id',
            'su.user_id',
            'su.user_role',
            's.classroom_id',
        ];

        $join = [
            '[><]' . self::$table . ' (su)' => ['s.id' => 'schedule_id'],
        ];

        return MysqlDB::getDB()->select(ScheduleModel::$table . ' (s)', $join, $columns, $where);

    }

    /**
     * 解绑课程的用户
     * @param $scheduleId
     * @param $userIds
     * @param $userRole
     * @param null $now
     * @return int|null
     */
    public static function unbindUser($scheduleId, $userIds, $userRole, $now = null)
    {
        if (empty($now)) {
            $now = time();
        }
        return MysqlDB::getDB()->updateGetCount(self::$table, [
            'status' => self::STATUS_CANCEL,
            'update_time' => $now
        ], [
            'schedule_id' => $scheduleId,
            'user_id' => $userIds,
            'user_role' => $userRole,
            'status' => self::STATUS_NORMAL
        ]);
    }

    /**
     * @param $userIds
     * @param $class_id
     * @param $beginTime
     * @return int|null
     */
    public static function cancelScheduleUsers($userIds,$class_id,$beginTime) {
        $where = [];
        if(!empty($userIds[ClassUserModel::USER_ROLE_S])){
            $where[] = "(su.user_role = ".ClassUserModel::USER_ROLE_S." and su.user_id in (".implode(',',$userIds[ClassUserModel::USER_ROLE_S])."))";
        }
        if(!empty($userIds[ClassUserModel::USER_ROLE_T])){
            $where[] = "(su.user_role = ".ClassUserModel::USER_ROLE_T." and su.user_id in (".implode(',',$userIds[ClassUserModel::USER_ROLE_T])."))";
        }
        $sql = "update ".self::$table." as su inner join ".ScheduleModel::$table." as s on s.id = su.schedule_id
          set su.status = ".self::STATUS_CANCEL." where s.start_time >= $beginTime 
          and s.class_id = $class_id and su.status = ".self::STATUS_NORMAL;
        if(!empty($where)) {
            $sql .= " and (".implode(" or ",$where).")";
        }
        $statement =  MysqlDB::getDB()->query($sql);
        if ($statement && $statement->errorCode() == MysqlDB::ERROR_CODE_NO_ERROR) {
            return $statement->rowCount();
        }
        return null;
    }

    /**
     * 修改学生状态为出席或请假
     * @param $scheduleId
     * @param $suIds
     * @param $userStatus
     * @param $time
     */
    public static function updateStudentStatus($scheduleId, $suIds, $userStatus, $time)
    {
        self::batchUpdateRecord([
            'user_status' => $userStatus,
            'update_time' => $time
        ], [
            'id' => $suIds,
            'schedule_id' => $scheduleId,
            'user_role' => self::USER_ROLE_STUDENT,
            'user_status' => self::STUDENT_STATUS_BOOK,
            'status' => self::STATUS_NORMAL
        ], false);
    }

    /**
     * 修改老师状态为出席或请假
     * @param $scheduleId
     * @param $suIds
     * @param $userStatus
     * @param $time
     */
    public static function updateTeacherStatus($scheduleId, $suIds, $userStatus, $time)
    {
        self::batchUpdateRecord([
            'user_status' => $userStatus,
            'update_time' => $time
        ], [
            'id' => $suIds,
            'schedule_id' => $scheduleId,
            'user_role' => [self::USER_ROLE_TEACHER, self::USER_ROLE_CLASS_TEACHER],
            'user_status' => self::TEACHER_STATUS_SET,
            'status' => self::STATUS_NORMAL
        ], false);
    }

    /**
     * 获取学生或老师的user_id
     * @param $scheduleId
     * @param $role
     * @return array
     */
    public static function getUserIds($scheduleId, $role)
    {
        return ScheduleUserModel::getRecords([
            'schedule_id' =>  $scheduleId,
            'status' => self::STATUS_NORMAL,
            'user_role' => $role
        ], 'user_id', false);
    }

    /**
     * 更新学生费用
     * @param $scheduleId
     * @param $studentId
     * @param $price
     * @param $time
     */
    public static function updateUserPrice($scheduleId, $studentId, $price, $time)
    {
        MysqlDB::getDB()->updateGetCount(self::$table, [
            'price' => $price,
            'update_time' => $time
        ], [
            'schedule_id' => $scheduleId,
            'status' => self::STATUS_NORMAL,
            'user_id' => $studentId,
            'user_role' => self::USER_ROLE_STUDENT
        ]);
    }
}