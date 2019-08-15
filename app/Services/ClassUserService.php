<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-19
 * Time: 19:18
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ClassTaskPriceModel;
use App\Models\ClassUserModel;
use App\Models\ScheduleUserModel;

class ClassUserService
{
    /**
     * @param $classId
     * @param $CUs
     * @param $ctIds null
     * @return bool
     */
    public static function bindCUs($classId, $CUs, $ctIds = null)
    {
        $now = time();
        $cus = [];
        $ctPrices = [];

        foreach ($CUs as $role => $users) {
            foreach ($users as $userId => $value) {
                if($role == ClassUserModel::USER_ROLE_S) {
                    if (count($ctIds) == 0 || count($value) != count($ctIds)) {
                        return false;
                    }

                    foreach ($value as $key => $price) {
                        $ctPrices[] = [
                            'class_id' => $classId,
                            'c_t_id' => $ctIds[$key],
                            'student_id' => $userId,
                            'price' => $price * 100,
                            'status' => ClassUserModel::STATUS_NORMAL,
                            'create_time' => $now
                        ];
                    }
                    $realRole = $role;
                } else {
                    $realRole = $value;
                }
                $cus[] = [
                    'status' => ClassUserModel::STATUS_NORMAL,
                    'class_id' => $classId,
                    'user_id' => $userId,
                    'user_role' => $realRole,
                    'create_time' => $now
                ];
            }
        }

        if (!empty($cus)) {
            $ret = ClassUserModel::addCU($cus);
            if (is_null($ret))
                return false;
        }

        if (!empty($ctPrices)) {
            $ret = ClassTaskPriceModel::batchInsert($ctPrices, false);
            if ($ret == false)
                return false;
        }
        return true;
    }

    /**
     * 解绑用户
     * @param $cuIds
     * @param $classId
     * @return int|null
     */
    public static function unBindUser($cuIds, $classId)
    {
        return ClassUserModel::updateCUStatus(['id' => $cuIds, 'class_id' => $classId], ClassUserModel::STATUS_CANCEL);
    }

    /**
     * 更新学生价格
     * @param $classId
     * @param $studentIds
     * @return int|null
     */
    public static function updateStudentPrice($classId, $studentIds = null)
    {
        $data['class_id'] = $classId;
        if (!empty($studentIds)) {
            $data['student_id'] = $studentIds;
        }
        return ClassTaskPriceModel::batchUpdateRecord(['status' => ClassUserModel::STATUS_CANCEL, 'update_time' => time()], $data, false);
    }

    /**
     * 格式化班级学生信息
     * @param $cu
     * @return mixed
     */
    public static function formatCU($cu)
    {
        $prices = 0;
        if (!empty($cu['price'])) {
            $prices = explode(',', $cu['price']);
            if (is_array($prices)) {
                $prices = array_map(function ($price) {return floatval($price / 100);}, $prices);
            }
        }
        $cu['price'] = $prices;
        $cu['cu_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_ROLE, $cu['user_role']);
        $cu['cu_status'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_STATUS, $cu['status']);
        return $cu;
    }

    /**
     * @param $students
     * @param $cts
     * @param $maxNum
     * @param int $originSId
     * @return array|bool
     */
    public static function checkStudent($students, $cts, $maxNum, $originSId = 0)
    {
        $studentIds = array_keys($students);
        if (count($students) > $maxNum) {
            return Valid::addErrors([], 'class_student', 'class_students_is_more_than_max');
        }
        $eStudents = StudentService::getStudentByIds($studentIds);
        if (count($students) != count($eStudents)) {
            return Valid::addErrors([], 'class_student', 'class_student_is_not_match');
        }

        foreach ($cts as $ct) {
            $orgClassId = empty($ct['class_id']) ? null : $ct['class_id'];
            // check class user
            $sts = ClassTaskModel::checkUserTime($studentIds, ClassUserModel::USER_ROLE_S, $ct['start_time'], $ct['end_time'], $ct['weekday'], $ct['expire_start_date'], $ct['expire_end_date'], $orgClassId);
            if (!empty($sts)) {
                return Valid::addErrors([], 'class_student', 'class_student_time_error');
            }

            // check schedule user
            list($startTime, $duration) = ScheduleService::formatClassTaskTime($ct);
            for ($i = 0; $i < $ct['period']; $i ++) {
                $endTime = $startTime + $duration;
                $checkStudent = ScheduleUserService::checkScheduleUser($studentIds,
                    ScheduleUserModel::USER_ROLE_STUDENT, $startTime, $endTime, $originSId, $orgClassId);
                if ($checkStudent !== true) {
                    return Valid::addErrors([], 'class_student', 'class_student_time_error');
                }

                $startTime += Util::TIMESTAMP_ONEWEEK;
            }

        }
        return true;
    }

    /**
     * @param $teachers
     * @param $cts
     * @param $classTeachers array
     * @param int $originSId
     * @return array|bool
     */
    public static function checkTeacher($teachers, $cts, $classTeachers = [], $originSId = 0)
    {
        $teacherIds = array_keys($teachers);
        $maxNum = 2 - count($classTeachers);
        if (count($teachers) > $maxNum) {
            return Valid::addErrors([], 'class_teacher', 'class_teacher_num_more_than_max');
        }
        $eTeachers = TeacherService::getTeacherByIds($teacherIds);
        if (count($teachers) != count($eTeachers)) {
            return Valid::addErrors([], 'class_teacher', 'class_teacher_is_not_match');
        }

        // 角色限制：1个老师，1个班主任
        $userRoles = !empty($classTeachers) ? array_column($classTeachers, 'user_role') : [];
        $roles = array_unique(array_values($teachers));
        if (count($roles) != count($teachers) || !empty(array_intersect($userRoles, $roles))) {
            return Valid::addErrors([], 'class_teacher', 'class_teacher_role_not_allow');
        }

        foreach ($cts as $ct) {
            $orgClassId = empty($ct['class_id']) ? null : $ct['class_id'];
            // check class user
            $sts = ClassTaskModel::checkUserTime($teacherIds, array(ClassUserModel::USER_ROLE_T, ClassUserModel::USER_ROLE_HT), $ct['start_time'], $ct['end_time'], $ct['weekday'], $ct['expire_start_date'], $ct['expire_end_date'], $orgClassId);
            if (!empty($sts)) {
                return Valid::addErrors([], 'class_teacher', 'class_teacher_time_error');
            }

            // check schedule user
            list($startTime, $duration) = ScheduleService::formatClassTaskTime($ct);
            for ($i = 0; $i < $ct['period']; $i ++) {
                $endTime = $startTime + $duration;
                $checkTeacher = ScheduleUserService::checkScheduleUser($teacherIds,
                    [ScheduleUserModel::USER_ROLE_TEACHER, ScheduleUserModel::USER_ROLE_CLASS_TEACHER],
                    $startTime, $endTime, $originSId, $orgClassId);
                if ($checkTeacher !== true) {
                    return Valid::addErrors([], 'class_teacher', 'class_teacher_time_error');
                }

                $startTime += Util::TIMESTAMP_ONEWEEK;
            }
        }
        return true;
    }

    /**
     * @param $where
     * @param $status
     * @return int|null
     */
    public static function updateCUStatus($where,$status)
    {
        return ClassUserModel::updateCUStatus($where,$status);
    }

    /**
     * @param $where
     * @param $status
     * @return int|null
     */
    public static function getUserClassInfo($where,$status)
    {
        $scheduleInfo =  ClassUserModel::getUserClassInfo($where,$status);
        return $scheduleInfo;
    }
}