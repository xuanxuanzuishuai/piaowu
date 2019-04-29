<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;

class ClassTaskService
{
    /**
     * @param $classId
     * @param $cts
     * @return bool
     */
    public static function addCTs($classId, $cts)
    {
        if (!empty($cts)) {
            foreach ($cts as $key => $ct) {
                $ct['class_id'] = $classId;
                $cts[$key] = $ct;
            }
        }
        if(!empty($cts['lesson_num'])) {
            unset($cts['lesson_num']);
        }
        return ClassTaskModel::batchInsert($cts);
    }

    /**
     * @param $classId
     * @param $userId
     * @param $userRole
     * @return bool
     */
    public static function bindUser($classId, $userId, $userRole)
    {
        $now = time();
        $insert = [];

        $insert[] = ['class_id' => $classId, 'user_id' => $userId, 'user_role' => $userRole, 'status' => ClassUserModel::STATUS_NORMAL, 'create_time' => $now];

        $cus = ClassUserModel::getCUListByClassId($classId);
        foreach ($cus as $cu) {
            if ($cu['user_id'] == $userId && $cu['user_role'] == $userRole && $cu['status'] == ClassUserModel::STATUS_NORMAL)
                $delIds[] = $cu['id'];
        }
        if (!empty($delIds))
            ClassUserService::unBindUser($delIds);
        if (!empty($insert))
            ClassUserModel::batchInsert($insert);
        return true;
    }

//    /**
//     * @param $params
//     * @param int $page
//     * @param int $count
//     * @return array
//     */
//    public static function getSTList($params, $page = 1, $count = 20)
//    {
//        $stIds = $result = [];
//        list($count, $sts) = ClassTaskModel::getSTList($params, $page, $count);
//        if(empty($sts)) {
//            return [0,[]];
//        }
//        foreach ($sts as $st) {
//            $stIds[] = $st['id'];
//            $result[$st['id']] = $st;
//        }
//
//            $stus = ClassUserModel::getSTUBySTIds($stIds);
//            foreach ($stus as $stu) {
//                if ($stu['user_role'] == ClassUserModel::USER_ROLE_S) {
//                    $result[$stu['st_id']]['students']++;
//                } else
//                    $result[$stu['st_id']]['teachers']++;
//            }
//
//
//        foreach($result as $st_id => $st) {
//            $result[$st_id] = self::formatCT($st);
//        }
//        return [$count, $result];
//    }
//
//    /**
//     * @param $stId
//     * @return array|null
//     */
//    public static function getSTDetail($stId)
//    {
//        $st = ClassTaskModel::getSTDetail($stId);
//        if (empty($st)) {
//            return null;
//        }
//
//        $stus = ClassUserModel::getSTUBySTIds([$st['id']]);
//        foreach ($stus as $stu) {
//            $stu = ClassUserService::formatSTU($stu);
//            if ($stu['user_role'] == ClassUserModel::USER_ROLE_S) {
//                $st['students'][] = $stu;
//            } else
//                $st['teachers'][] = $stu;
//        }
//        $st = self::formatCT($st);
//        return $st;
//    }

    /**
     * @param $ct
     * @return array|bool
     */
    public static function checkCT($ct)
    {
        $res = ClassTaskModel::checkCT($ct);
        return empty($res) ? true : $res;
    }

    /**
     * @param $pcts
     * @param null $classId
     * @return array
     */
    public static function checkCTs($pcts,$classId = null)
    {
        global $orgId;
        $now = time();
        $cts = [];
        $lessonNum = 0;
        foreach ($pcts as $pct) {
            if (empty($pct['course_id'])) {
                return Valid::addErrors([], 'class_course', 'class_course_not_exist');
            }
            $course = CourseService::getCourseById($pct['course_id']);
            if (empty($course)) {
                return Valid::addErrors([], 'class_course', 'class_course_not_exist');
            }
            if (empty($pct['classroom_id'])) {
                return Valid::addErrors([], 'class_classroom', 'class_classroom_not_exist');
            }
            $classroom = ClassroomService::getById($pct['classroom_id']);
            if (empty($classroom)) {
                return Valid::addErrors([], 'class_classroom', 'class_classroom_not_exist');
            }
            if (empty($pct['start_time'])) {
                return Valid::addErrors([], 'class_start_time', 'class_start_time_not_exist');
            }
            if (!isset($pct['weekday'])) {
                return Valid::addErrors([], 'class_weekday', 'class_weekday_not_exist');
            }
            if (!in_array($pct['weekday'], [0, 1, 2, 3, 4, 5, 6])) {
                return Valid::addErrors([], 'class_weekday', 'class_weekday_is_invalid');
            }
            if (empty($pct['expire_start_date'])) {
                return Valid::addErrors([], 'class_start_time', 'class_expire_start_date_not_exist');
            }

            if (empty($pct['period'])) {
                return Valid::addErrors([], 'class_period', 'class_period_not_exist');
            }
            $pct['expire_end_date'] = empty($pct['expire_end_date']) ? date("Y-m-d",strtotime($pct['expire_start_date']) + (7 * ($pct['period'] - 1) + 1) * 86400) : $pct['expire_end_date'];
            if($pct['expire_end_date'] <= $pct['expire_start_date']) {
                return Valid::addErrors([], 'class_start_time', 'class_expire_end_date_is_invalid');
            }
            $endTime = date("H:i", strtotime($pct['start_time']) + $course['duration']);
            $ct = [
                'classroom_id' => $pct['classroom_id'],
                'start_time' => $pct['start_time'],
                'end_time' => $endTime,
                'course_id' => $pct['course_id'],
                'weekday' => $pct['weekday'],
                'create_time' => $now,
                'status' => ClassTaskModel::STATUS_NORMAL,
                'org_id' => $orgId,
                'expire_start_date' => $pct['expire_start_date'],
                'expire_end_date' => $pct['expire_end_date'],
                'period' => $pct['period'],
            ];
            $lessonNum += $pct['period'];
            if(!empty($classId)){
                $ct['class_id'] = $classId;
            }
            $res = self::checkCT($ct);
            if ($res !== true) {

                return Valid::addErrors(['data' => ['result' => $res],'code'=>1], 'class_task_classroom', 'class_task_classroom_error');
            }
            $startTimes[$pct['start_time']] = [$pct['start_time'], $endTime];
            $cts[] = $ct;
        }
        ksort($startTimes);
        foreach ($startTimes as $key => $time) {
            $next = next($startTimes);
            if (!empty($next) && $next[0] < $time[1] && $next[1] > $time[0]) {
                return Valid::addErrors([], 'class_start_time', 'class_start_time_conflict');
            }
        }
        $cts['lesson_num'] = $lessonNum;
        return $cts;
    }


    /**
     * @param $where
     * @param $status
     * @return bool
     */
    public static function updateCTStatus($where,$status)
    {
        return ClassTaskModel::updateCTStatus($where,$status);
    }

    /**
     * @param $ct
     * @return mixed
     */
    public static function formatCT($ct)
    {
        $ct['course_type'] = DictService::getKeyValue(Constants::DICT_COURSE_TYPE, $ct['course_type']);
        return $ct;
    }

    /**
     * @param $cts
     * @return array
     */
    public static function getStartEndDate($cts) {
        $min = $max = '';
        foreach($cts as $key => $ct) {
            $ct['expire_end_date'] = empty($ct['expire_end_date']) ? $ct['expire_start_date'] + (7 * ($ct['period'] - 1) + 1) * 86400 : $ct['expire_end_date'];
            $min = $min > $ct['expire_start_date']?$ct['expire_start_date']:$min;
            $max = $max < $ct['expire_end_date'] ? $ct['expire_end_date'] : $max;
        }
        return [$min,$max];
    }

}
