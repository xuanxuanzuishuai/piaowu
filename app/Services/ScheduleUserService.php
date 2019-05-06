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
     * @param $time
     * @return int|null
     */
    public static function unBindUser($scheduleId, $time) {
        return ScheduleUserModel::unbindUser($scheduleId, $time);
    }

    /**
     * @param $userIds
     * @param $userRoles
     * @param $startTime
     * @param $endTime
     * @param $orgId
     * @return array|bool
     */
    public static function checkScheduleUser($userIds, $userRoles, $startTime, $endTime, $orgId)
    {
        $sus = ScheduleUserModel::checkScheduleUser($userIds, $userRoles, $startTime, $endTime, $orgId);
        return empty($sus) ? true : $sus;
    }

    /**
     * @param $users
     * @param $st_id
     * @param $beginDate
     * @return int|null
     */
    public static function cancelScheduleUsers($users,$st_id,$beginDate) {
        return ScheduleUserModel::cancelScheduleUsers($users,$st_id,$beginDate);
    }

    /**
     * 学生、老师签到
     * @param $scheduleId
     * @param $suIds
     * @param $students
     * @param $operatorId
     * @return array|bool
     */
    public static function signIn($scheduleId, $suIds, $students, $operatorId)
    {
        SimpleLogger::info('student and teacher sign in ', $suIds);

        foreach ($students as $student) {
            if (in_array($student['id'], $suIds) && $student['user_status'] == ScheduleUserModel::STUDENT_STATUS_BOOK) {
                // student account
                $result = StudentAccountService::reduceSA($student['user_id'], $student['price'] * 100, $operatorId, '下课');
                if ($result !== true) {
                    return $result;
                }
            }
        }

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
     * 添加课程学生、老师
     * @param $students
     * @param $teachers
     * @param $scheduleId
     * @param $time
     * @return bool
     */
    public static function addScheduleUser($students, $teachers, $scheduleId, $time)
    {
        $users = [];
        foreach($students as $key => $value) {
            $users[] = [
                'price' => $value * 100,
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
}