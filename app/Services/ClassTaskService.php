<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\STClassModel;

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
            if(!empty($cts['lesson_num'])) {
                unset($cts['lesson_num']);
            }

            foreach ($cts as $key => $ct) {
                $ct['class_id'] = $classId;
                $cts[$key] = $ct;
            }
        }
        return ClassTaskModel::batchInsert($cts);
    }


    /**
     * @param $ct
     * @param $classStatus
     * @return array|bool
     */
    public static function checkCT($ct, $classStatus)
    {
        $res = ClassTaskModel::checkCT($ct, $classStatus);
        return empty($res) ? true : $res;
    }

    /**
     * @param $pcts
     * @param null $classId
     * @return array
     */
    public static function checkCTs($pcts, $classId = null)
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
                return Valid::addErrors([], 'class_classroom', 'class_classroom_is_required');
            }
            $classroom = ClassroomService::getById($pct['classroom_id']);
            if (empty($classroom)) {
                return Valid::addErrors([], 'class_classroom', 'class_classroom_is_required');
            }
            if (empty($pct['start_time'])) {
                return Valid::addErrors([], 'class_start_time', 'class_start_time_is_required');
            }
            if (!isset($pct['weekday'])) {
                return Valid::addErrors([], 'class_weekday', 'class_weekday_is_required');
            }
            if (!in_array($pct['weekday'], [0, 1, 2, 3, 4, 5, 6])) {
                return Valid::addErrors([], 'class_weekday', 'class_weekday_is_invalid');
            }
            if (empty($pct['expire_start_date'])) {
                return Valid::addErrors([], 'class_start_time', 'expire_start_date_is_required');
            }

            if (empty($pct['period'])) {
                return Valid::addErrors([], 'class_period', 'class_period_is_required');
            }

            $beginDate = $pct['expire_start_date'];
            $weekday = date("w", strtotime($beginDate));
            if ($weekday <= $pct['weekday']) {
                $beginTime = strtotime($beginDate) + 86400 * ($pct['weekday'] - $weekday);
            } else {
                $beginTime = strtotime($beginDate) + 86400 * (7 - ($weekday - $pct['weekday']));
            }
            $pct['expire_start_date'] = date("Y-m-d", $beginTime);
            $pct['expire_end_date'] = empty($pct['expire_end_date']) ? date("Y-m-d",$beginTime + (7 * ($pct['period'] - 1) + 1) * 86400) : $pct['expire_end_date'];
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

            foreach ($cts as $ct1) {
                if ($ct['weekday'] == $ct1['weekday']) {
                    $time = $ct['start_time'] < $ct1['end_time'] && $ct['end_time'] > $ct1['start_time'];
                    $date = $ct['expire_start_date'] < $ct1['expire_end_date'] && $ct['expire_end_date'] > $ct1['expire_start_date'];
                    if ($time && $date) {
                        return Valid::addErrors([], 'class_start_time', 'class_start_time_conflict');
                    }
                }
            }

            $res = self::checkCT($ct, [STClassModel::STATUS_NORMAL, STClassModel::STATUS_BEGIN, STClassModel::STATUS_CHANGE]);
            if ($res !== true) {
                return Valid::addErrors(['data' => ['result' => $res], 'code' => 1], 'class_task_classroom', 'class_task_classroom_error');
            }
            $cts[] = $ct;
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
     * @param $classId
     * @return array
     */
    public static function getCTIds($classId)
    {
        return ClassTaskModel::getRecords(['class_id' => $classId, 'status' => ClassTaskModel::STATUS_NORMAL], 'id');
    }
}
