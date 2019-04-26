<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-16
 * Time: 18:04
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\ScheduleModel;
use App\Models\ScheduleTaskModel;
use App\Models\ScheduleTaskUserModel;

class ScheduleTaskService
{
    /**
     * @param $st
     * @param array $students
     * @param array $teachers
     * @return array|bool
     */
    public static function addST($st, $students = array(), $teachers = array())
    {
        $stus = [];
        $stId = ScheduleTaskModel::addST($st);
        if (is_null($stId)) {
            return false;
        }
        $stus[ScheduleTaskUserModel::USER_ROLE_S] = $students;
        $stus[ScheduleTaskUserModel::USER_ROLE_T] = $teachers;

        if (!empty($stus)) {
            $res = ScheduleTaskUserService::bindSTUs([$stId], $stus);
            if ($res == false) {
                return $res;
            }

        }
        return $stId;
    }

    /**
     * @param $stId
     * @param $userId
     * @param $userRole
     * @return bool
     */
    public static function bindUser($stId, $userId, $userRole)
    {
        $now = time();
        $insert = [];

        $insert[] = ['st_id' => $stId, 'user_id' => $userId, 'user_role' => $userRole, 'status' => ScheduleTaskUserModel::STATUS_NORMAL, 'create_time' => $now];

        $stus = ScheduleTaskUserModel::getSTUBySTIds([$stId]);
        foreach ($stus as $stu) {
            if ($stu['user_id'] == $userId && $stu['user_role'] == $userRole && $stu['status'] == ScheduleTaskUserModel::STATUS_NORMAL)
                $delIds[] = $stu['id'];
        }
        if (!empty($delIds))
            ScheduleTaskUserService::unBindUser($delIds);
        if (!empty($insert))
            ScheduleTaskUserModel::batchInsert($insert);
        return true;
    }

    /**
     * @param $params
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getSTList($params, $page = 1, $count = 20)
    {
        $stIds = $result = [];
        list($count, $sts) = ScheduleTaskModel::getSTList($params, $page, $count);
        if(empty($sts)) {
            return [0,[]];
        }
        foreach ($sts as $st) {
            $stIds[] = $st['id'];
            $result[$st['id']] = $st;
        }

            $stus = ScheduleTaskUserModel::getSTUBySTIds($stIds);
            foreach ($stus as $stu) {
                if ($stu['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                    $result[$stu['st_id']]['students']++;
                } else
                    $result[$stu['st_id']]['teachers']++;
            }


        foreach($result as $st_id => $st) {
            $result[$st_id] = self::formatST($st);
        }
        return [$count, $result];
    }

    /**
     * @param $stId
     * @return array|null
     */
    public static function getSTDetail($stId)
    {
        $st = ScheduleTaskModel::getSTDetail($stId);
        if (empty($st)) {
            return null;
        }

        $stus = ScheduleTaskUserModel::getSTUBySTIds([$st['id']]);
        foreach ($stus as $stu) {
            $stu = ScheduleTaskUserService::formatSTU($stu);
            if ($stu['user_role'] == ScheduleTaskUserModel::USER_ROLE_S) {
                $st['students'][] = $stu;
            } else
                $st['teachers'][] = $stu;
        }
        $st = self::formatST($st);
        return $st;
    }

    /**
     * @param $st
     * @return array|bool
     */
    public static function checkST($st)
    {
        $sts = ScheduleTaskModel::checkSTList($st);
        return empty($sts) ? true : $sts;
    }

    /**
     * @param $students
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param $expireStartDate
     * @param $maxNum
     * @param null $orgSTId
     * @return array|bool
     */
    public static function checkStudent($students, $start_time, $end_time, $weekday, $expireStartDate,$maxNum,$orgSTId = null)
    {
        $studentIds = array_keys($students);
        $result = true;
        if (count($students) > $maxNum) {
            $result = Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_num_more_than_max');
        }
        $eStudents = StudentService::getStudentByIds($studentIds);
        if (count($students) != count($eStudents))
        {
            $result = Valid::addErrors([], 'schedule_task_student', 'schedule_task_student_is_not_match');
        }
        $sts = ScheduleTaskModel::getSTListByUser($studentIds, ScheduleTaskUserModel::USER_ROLE_S, $start_time, $end_time, $weekday, $expireStartDate,$orgSTId);
        if(!empty($sts)) {
            $result = Valid::addErrors(['data' => ['result' => $sts]], 'schedule_task_student', 'schedule_task_student_time_error');
        }
        return $result;
    }

    /**
     * @param $teachers
     * @param $start_time
     * @param $end_time
     * @param $weekday
     * @param $expireStartDate
     * @param $maxNum
     * @param null $orgSTId
     * @return array|bool
     */
    public static function checkTeacher($teachers, $start_time, $end_time, $weekday, $expireStartDate, $maxNum,$orgSTId = null)
    {
        $result = true;
        $teacherIds = array_keys($teachers);
        if (count($teachers) > $maxNum) {
            $result = Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_num_more_than_max');
        }
        $eTeachers = TeacherService::getTeacherByIds($teacherIds);
        if (count($teachers) != count($eTeachers))
        {
            $result = Valid::addErrors([], 'schedule_task_teacher', 'schedule_task_teacher_is_not_match');
        }
        $sts = ScheduleTaskModel::getSTListByUser($teacherIds, ScheduleTaskUserModel::USER_ROLE_T, $start_time, $end_time, $weekday, $expireStartDate,$orgSTId);
        if(!empty($sts)){
            $result = Valid::addErrors(['data' => ['result' => $sts]], 'schedule_task_teacher', 'schedule_task_teacher_time_error');
        }
        return $result;
    }

    /**
     * @param $st
     * @return bool
     */
    public static function modifyST($st)
    {
        return ScheduleTaskModel::modifyST($st);
    }

    /**
     * @param $st
     * @return mixed
     */
    public static function formatST($st) {
        if($st['status'] == ScheduleTaskModel::STATUS_NORMAL) {
            $num = is_array($st['students'])?count($st['students']):$st['students'];
            $st['st_status'] = $st['class_highest'] > $num ?ScheduleTaskModel::STATUS_UNFULL:ScheduleTaskModel::STATUS_FULL;
        }
        else {
            $st['st_status'] = $st['status'];
        }
        $st['st_status'] = DictService::getKeyValue(Constants::DICT_TYPE_SCHEDULE_TASK_STATUS,$st['st_status']);
        $st['course_type'] =  DictService::getKeyValue(Constants::DICT_COURSE_TYPE,$st['course_type']);
        return $st;
    }
}
