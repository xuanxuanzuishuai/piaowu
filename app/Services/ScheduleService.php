<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-22
 * Time: 16:11
 */

namespace App\Services;

use App\Libs\Constants;
use App\Models\ScheduleModel;
use App\Models\ClassUserModel;
use App\Models\ScheduleUserModel;

class ScheduleService
{

    /**
     * @param $class
     * @return bool
     */
    public static function beginSchedule($class) {
        $now = time();
        foreach($class['class_tasks'] as $ct) {
            $beginDate = $ct['expire_start_date'];
            $endDate = $ct['expire_end_date'];
            $weekday = date("w");
            if ($weekday <= $ct['weekday']) {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * ($ct['weekday'] - $weekday);
            } else {
                $beginTime = strtotime($beginDate . " " . $ct['start_time']) + 86400 * (7 - ($weekday - $ct['weekday']));
            }
            for($i=0;$i<$ct['period'];$i++) {
                $schedule = [
                    'classroom_id'=>$ct['classroom_id'],
                    'course_id'=>$ct['course_id'],
                    'duration'=>$ct['duration'],
                    'start_time'=>$beginTime,
                    'end_time' => $beginTime + $ct['duration'],
                    'create_time' => $now,
                    'status' => ScheduleModel::STATUS_BOOK,
                    'org_id' => $class['org_id'],
                    'class_id' => $class['id'],
                ];
                $sId = ScheduleModel::insertSchedule($schedule);
                if(empty($sId)) {
                    return false;
                }
                $users = [];
                foreach($class['students']  as $student) {
                    if($student['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $users[] = ['price'=>$student['price'],'schedule_id' => $sId, 'user_id' => $student['user_id'], 'user_role' => $student['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::STUDENT_STATUS_BOOK];
                    }
                }
                foreach($class['teachers']  as $teacher) {
                    if($teacher['status'] == ScheduleUserModel::STATUS_NORMAL) {
                        $users[] = ['price'=> 0 ,'schedule_id' => $sId, 'user_id' => $teacher['user_id'], 'user_role' => $teacher['user_role'], 'status' => ScheduleModel::STATUS_BOOK, 'create_time' => $now, 'user_status' => ScheduleUserModel::TEACHER_STATUS_SET];
                    }
                }
                $flag = ScheduleUserModel::insertSUs($users);
                if($flag == false)
                    return false;
                $beginTime += 7*86400;
                if($endDate <= date("Y-m-d",$beginTime))
                {
                    break;
                }
            }
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
                if ($su['user_role'] == ClassUserModel::USER_ROLE_S) {
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
            if ($su['user_role'] == ClassUserModel::USER_ROLE_S) {
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
        $su['su_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_ROLE,$su['user_role']);
        $su['su_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_USER_STATUS,$su['status']);
        if($su['user_role'] == ClassUserModel::USER_ROLE_S) {
            $su['student_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STUDENT_STATUS, $su['user_status']);
        }
        if($su['user_role'] == ClassUserModel::USER_ROLE_T) {
            $su['teacher_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TEACHER_STATUS, $su['user_status']);
        }
        $su['price'] = floatval($su['price']/100);
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

    /**
     * @param $stId
     * @return bool
     */
    public static function cancelScheduleByClassId($stId) {
        return ScheduleModel::modifyScheduleByClassId(['status'=>ScheduleModel::STATUS_CANCEL,'update_time'=>time()],['class_id'=>$stId,'status'=>ScheduleModel::STATUS_BOOK]);
    }

    /**
     * @param $stId
     * @param $users
     * @param $userRole
     * @return bool
     */
    public static function bindSUs($stId,$users,$userRole) {
        $sus = [];
        $now = time();
        list($count,$schedules) = self::getList(['class_id'=>$stId,'status'=>ScheduleModel::STATUS_BOOK]);
        foreach($schedules as $schedule) {
            foreach($users as $userId => $value){
                if($userRole == ClassUserModel::USER_ROLE_S) {
                    $price = $value * 100;
                    $userRole = $userRole;
                }
                else {
                    $price = 0;
                    $userRole = $value;
                }
                $suStatus = $schedule['status'] != ScheduleModel::STATUS_BOOK?ScheduleUserModel::STATUS_CANCEL:ScheduleUserModel::STATUS_NORMAL;
                $userStatus = $userRole == ClassUserModel::USER_ROLE_S ? ScheduleUserModel::STUDENT_STATUS_BOOK: ScheduleUserModel::TEACHER_STATUS_SET;
                $sus[] = ['price'=>$price,'schedule_id'=>$schedule['id'],'user_id'=>$userId,'user_role'=>$userRole,'user_status'=>$userStatus,'status'=>$suStatus,'create_time'=> $now];
            }
        }
        if (!empty($sus)) {
            $ret = ScheduleUserModel::insertSUs($sus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * @param $scheduleId
     * @return int|null
     */
    public static function finish($scheduleId) {
        return ScheduleModel::updateRecord($scheduleId,['status'=>ScheduleModel::STATUS_FINISH,'update_time'=>time()]);
    }

    /** 学员上课记录
    * @param $orgId
    * @param $page
    * @param $count
    * @param $params
    * @return array
    */
    public static function attendRecord($orgId, $page, $count, $params)
    {
        list($records, $total) = ScheduleModel::attendRecord($orgId, $page, $count, $params);

        foreach ($records as &$r) {
            $r['status']   = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_STATUS, $r['status']);
            $r['duration'] /= 60;
        }

        return [$records, $total];
    }
}