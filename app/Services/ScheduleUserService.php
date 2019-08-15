<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-24
 * Time: 10:17
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\ScheduleUserModel;

class ScheduleUserService
{
    /**
     * @param $scheduleId
     * @param $userIds
     * @param $userRole
     * @param $time
     * @return int|null
     */
    public static function unBindUser($scheduleId, $userIds, $userRole, $time)
    {
        if (empty($userIds)) {
            return null;
        }
        return ScheduleUserModel::unbindUser($scheduleId, $userIds, $userRole, $time);
    }

    /**
     * @param $userIds
     * @param $userRoles
     * @param $startTime
     * @param $endTime
     * @param int $orgSId
     * @param $orgClassId
     * @return array|bool
     */
    public static function checkScheduleUser($userIds, $userRoles, $startTime, $endTime, $orgSId, $orgClassId)
    {
        $sus = ScheduleUserModel::checkScheduleUser($userIds, $userRoles, $startTime, $endTime, $orgSId, $orgClassId);
        return empty($sus) ? true : $sus;
    }

    /**
     * 解绑课次用户
     * @param $users
     * @param $st_id
     * @param $beginDate
     * @return int|null
     */
    public static function cancelScheduleUsers($users, $st_id, $beginDate)
    {
        return ScheduleUserModel::cancelScheduleUsers($users, $st_id, $beginDate);
    }

    /**
     * 学生、老师签到
     * @param $scheduleId
     * @param $suIds
     * @return array|bool
     */
    public static function signIn($scheduleId, $suIds)
    {
        SimpleLogger::info('student and teacher sign in ', $suIds);

        $now = time();
        // student sign
        ScheduleUserModel::updateStudentStatus($scheduleId, $suIds, ScheduleUserModel::STUDENT_STATUS_ATTEND, $now);
        // teacher sign
        ScheduleUserModel::updateTeacherStatus($scheduleId, $suIds, ScheduleUserModel::TEACHER_STATUS_ATTEND, $now);
        return true;
    }

    /**
     * 学生、老师请假
     * @param $scheduleId
     * @param $suIds
     */
    public static function takeOff($scheduleId, $suIds)
    {
        SimpleLogger::info('student and teacher take off ', $suIds);
        $now = time();

        // student take off
        ScheduleUserModel::updateStudentStatus($scheduleId, $suIds, ScheduleUserModel::STUDENT_STATUS_LEAVE, $now);

        // teacher takeoff
        ScheduleUserModel::updateTeacherStatus($scheduleId, $suIds, ScheduleUserModel::TEACHER_STATUS_LEAVE, $now);
    }

    /**
     * 更新学生状态已付费
     * @param $id
     */
    public static function deduct($id)
    {
        ScheduleUserModel::updateRecord($id, ['is_deduct' => ScheduleUserModel::DEDUCT_STATUS, 'update_time' => time()], false);
    }

    /**
     * 添加课程学生、老师
     * @param $students
     * @param $teachers
     * @param $scheduleId
     * @param $time
     * @return bool
     */
    public static function addScheduleUser($students, $teachers, $scheduleId, $time)
    {
        if (empty($students) && empty($teachers)) {
            return true;
        }

        $users = [];
        foreach($students as $key => $value) {
            $users[] = [
                'price' => $value[0] * 100,
                'schedule_id' => $scheduleId,
                'user_id' => $key,
                'user_role' => ScheduleUserModel::USER_ROLE_STUDENT,
                'status' => ScheduleUserModel::STATUS_NORMAL,
                'create_time' => $time,
                'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK
            ];
        }
        foreach($teachers as $key => $value) {
            $users[] = [
                'price' => 0,
                'schedule_id' => $scheduleId,
                'user_id' => $key,
                'user_role' => $value,
                'status' => ScheduleUserModel::STATUS_NORMAL,
                'create_time' => $time,
                'user_status' => ScheduleUserModel::TEACHER_STATUS_SET
            ];
        }
        return ScheduleUserModel::insertSUs($users);
    }

    /**
     * 更新学生price
     * @param $scheduleId
     * @param $studentId
     * @param $price
     * @param $time
     */
    public static function updateSchedulePrice($scheduleId, $studentId, $price, $time)
    {
        ScheduleUserModel::updateUserPrice($scheduleId, $studentId, $price, $time);
    }
}