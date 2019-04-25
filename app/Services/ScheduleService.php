<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\ScheduleModel;
use App\Models\ScheduleTaskUserModel;
use App\Models\ScheduleUserModel;

class ScheduleService
{

    /**
     * @param $st
     * @param $params
     * @param $beginTime
     * @return bool
     */
    public static function beginSchedule($st,$params,$beginTime) {
        $now = time();
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
                return false;
            }
            $users = [];
            foreach($st['students']  as $student) {
                if($student['status'] == ScheduleUserModel::STATUS_NORMAL) {
                    $users[] = ['schedule_id' => $sId, 'user_id' => $student['user_id'], 'user_role' => $student['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK];
                }
            }
            foreach($st['teachers']  as $teacher) {
                if($teacher['status'] == ScheduleUserModel::STATUS_NORMAL) {
                    $users[] = ['schedule_id' => $sId, 'user_id' => $teacher['user_id'], 'user_role' => $teacher['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::TEACHER_STATUS_SET];
                }
            }
            $flag = ScheduleUserModel::insertSUs($users);
            if($flag == false)
                return false;
            $beginTime += 7*86400;
        }
        return true;
    }

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getList($params,$page = -1,$count = 20) {
        $sIds = $result = [];
        list($count, $schedules) = ScheduleModel::getList($params, $page, $count);
        foreach ($schedules as $schedule) {
            $schedule = self::formatSchedule($schedule);
            $sIds[] = $schedule['id'];
            $result[$schedule['id']] = $schedule;
        }
        if(!empty($sIds)) {
            $sus = ScheduleUserModel::getSUBySIds($sIds);
            foreach ($sus as $su) {
                $su = self::formatScheduleUser($su);
                if ($su['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                    $result[$su['schedule_id']]['students']++;
                } else
                    $result[$su['schedule_id']]['teachers']++;
            }
        }
        return [$count, $result];
    }

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

    /**
     * @param $schedule
     * @return mixed
     */
    public static function formatSchedule($schedule) {
        $schedule['s_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS,$schedule['status']);
        $schedule['course_type'] = DictService::getKeyValue(Constants::DICT_COURSE_TYPE,$schedule['course_type']);
        return $schedule;
    }

    /**
     * @param $su
     * @return mixed
     */
    public static function formatScheduleUser($su) {
        $su['su_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_ROLE,$su['user_role']);
        $su['su_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_STATUS,$su['status']);
        if($su['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
            $su['student_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STUDENT_STATUS, $su['user_status']);
        }
        if($su['user_role'] == ScheduleTaskUserModel::USER_ROLE_T) {
            $su['teacher_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TEACHER_STATUS, $su['user_status']);
        }
        return $su;
    }

    /**
     * @param $schedule
     * @return array|bool
     */
    public static function checkSchedule($schedule){
        $schedules = ScheduleModel::checkSchedule($schedule);
        return empty($schedules) ? true : $schedules;
    }

    /**
     * @param $newSchedule
     * @return bool
     */
    public static function modifySchedule($newSchedule) {
        return ScheduleModel::modifySchedule($newSchedule);
    }

    public static function cancelScheduleBySTId($stId) {
        return ScheduleModel::modifyScheduleBySTId(['status'=>ScheduleModel::STATUS_CANCEL,'update_time'=>time()],['st_id'=>$stId,'status'=>ScheduleModel::STATUS_BOOK]);
    }

    public static function bindSUs($stId,$userIds,$userRole) {
        $sus = [];
        $now = time();
        list($count,$schedules) = self::getList(['st_id'=>$stId,'status'=>ScheduleModel::STATUS_BOOK]);
        foreach($schedules as $schedule) {
            foreach($userIds as $userId){
                $suStatus = $schedule['status'] != ScheduleModel::STATUS_BOOK?ScheduleUserModel::STATUS_CANCEL:ScheduleUserModel::STATUS_NORMAL;
                $userStatus = $userRole == ScheduleTaskUserModel::USER_ROLE_S ? ScheduleUserModel::STUDENT_STATUS_BOOK: ScheduleUserModel::TEACHER_STATUS_SET;
                $sus[] = ['schedule_id'=>$schedule['id'],'user_id'=>$userId,'user_role'=>$userRole,'user_status'=>$userStatus,'status'=>$suStatus,'create_time'=> $now];
            }
        }
        SimpleLogger::error('mmmm',[$schedules,$userIds,$sus]);
        if (!empty($sus)) {
            $ret = ScheduleUserModel::insertSUs($sus);
            if (is_null($ret))
                return false;
        }
        return true;
    }
}