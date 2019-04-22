<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:11
 */

namespace App\Services;

use App\Models\ScheduleModel;
use App\Models\ScheduleTaskUserModel;
use App\Models\ScheduleUserModel;

class ScheduleService
{

    public static function beginSchedule($st,$params,$beginTime) {
        $now = time();
        $flag = true;
        for($i=0;$i<$params['period'];$i++) {
            $schedule = [
                'classroom_id'=>$st['classroom_id'],
                'course_id'=>$st['course_id'],
                'duration'=>$st['duration'],
                'start_time'=>$beginTime,
                'end_time' => $beginTime + $st['duration'],
                'create_time' => $now,
                'status' => ScheduleModel::STATUS_BOOK,
                'org_id' => $st['org_id'],
                'st_id' => $st['id'],
            ];
            $sId = ScheduleModel::insertSchedule($schedule);
            if(empty($sId)) {
                $flag = false;
                break;
            }
            $users = [];
            foreach($st['students']  as $student) {
                if($student['status'] == ScheduleTaskUserModel::STATUS_NORMAL) {
                    $users[] = ['schedule_id' => $sId, 'user_id' => $student['user_id'], 'user_role' => $student['user_role'], 'status' => ScheduleUserModel::STATUS_NORMAL, 'create_time' => $now, 'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK];
                }
            }
            foreach($st['teachers']  as $teacher) {
                if($teacher['status'] == ScheduleTaskUserModel::STATUS_NORMAL) {
                    $users[] = ['schedule_id' => $sId, 'user_id' => $teacher['user_id'], 'user_role' => $teacher['user_role'], 'status' => ScheduleUserModel::STATUS_NORMAL, 'create_time' => $now, 'user_status' => ScheduleUserModel::TEACHER_STATUS_SET];
                }
            }
            $flag = ScheduleUserModel::insertSUs($users);
            $beginTime += 7*86400;
        }
        return $flag;
    }

    public static function getList($parmas,$page = -1,$)

    /**
     * @param $sId
     * @return null
     */
    public static function getDetail($sId) {
        $schedule = ScheduleModel::getDetail($sId);
        if (empty($schedule)) {
            return null;
        }
        $schedule = self::formatSchedule($schedule);
        $sus = ScheduleUserModel::getSUBySIds([$schedule['id']]);
        foreach ($sus as $su) {
            $su = self::formatScheduleUser($su);
            if ($su['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                $schedule['students'][] = $su;
            } else
                $schedule['teachers'][] = $su;
        }
        return $schedule;
    }

    public static function formatSchedule($schedule) {
        return $schedule;
    }

    public static function formatScheduleUser($su) {
        return $su;
    }

}