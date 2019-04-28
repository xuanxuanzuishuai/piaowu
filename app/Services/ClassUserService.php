<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-19
 * Time: 19:18
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Valid;
use App\Models\ClassTaskModel;
use App\Models\ClassUserModel;

class ClassUserService
{
    /**
     * @param $classId
     * @param $CUs
     * @return bool
     */
    public static function bindCUs($classId, $CUs)
    {
        $now = time();
        $cus = [];
        foreach ($CUs as $role => $users) {

            foreach ($users as $userId => $value) {
                if($role == ClassUserModel::USER_ROLE_S) {
                    $value = $value * 100;
                    $realRole = $role ;
                }
                else {
                    $value = 0;
                    $realRole = $value;
                }
                $cus[] = ['status' => ClassUserModel::STATUS_NORMAL, 'class_id' => $classId, 'user_id' => $userId, 'price' => $value, 'user_role' => $realRole, 'create_time' => $now];
            }
        }

        if (!empty($cus)) {
            $ret = ClassUserModel::addCU($cus);
            if (is_null($ret))
                return false;
        }
        return true;
    }

    /**
     * @param $cuIds
     * @param $classId
     * @return int|null
     */
    public static function unBindUser($cuIds, $classId)
    {
        return ClassUserModel::updateCUStatus(['id' => $cuIds, 'class_id' => $classId], ClassUserModel::STATUS_CANCEL);
    }

    /**
     * @param $cu
     * @return mixed
     */
    public static function formatCU($cu)
    {
        $cu['cu_user_role'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_ROLE, $cu['user_role']);
        $cu['cu_status'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_STATUS, $cu['status']);
        return $cu;
    }

    /**
     * @param $students
     * @param $cts
     * @param $maxNum
     * @return array|bool
     */
    public static function checkStudent($students, $cts, $maxNum)
    {
        $studentIds = array_keys($students);
        $result = true;
        if (count($students) > $maxNum) {
            $result = Valid::addErrors([], 'class_student', 'class_student_num_more_than_max');
        }
        $eStudents = StudentService::getStudentByIds($studentIds);
        if (count($students) != count($eStudents)) {
            $result = Valid::addErrors([], 'class_student', 'class_student_is_not_match');
        }

        foreach ($cts as $ct) {
            $orgClassId = empty($ct['class_id']) ? null : $ct['class_id'];
            $sts = ClassTaskModel::checkUserTime($studentIds, ClassUserModel::USER_ROLE_S, $ct['start_time'], $ct['end_time'], $ct['weekday'], $ct['expire_start_date'], $ct['expire_end_date'], $orgClassId);
            if (!empty($sts)) {
                $result = Valid::addErrors(['data' => ['result' => $sts]], 'class_student', 'class_student_time_error');
            }
            return $result;
        }
        return true;
    }

    /**
     * @param $teachers
     * @param $cts
     * @param $maxNum
     * @return array|bool
     */
    public static function checkTeacher($teachers, $cts, $maxNum)
    {
        $result = true;
        $teacherIds = array_keys($teachers);
        if (count($teachers) > $maxNum) {
            $result = Valid::addErrors([], 'class_teacher', 'class_teacher_num_more_than_max');
        }
        $eTeachers = TeacherService::getTeacherByIds($teacherIds);
        if (count($teachers) != count($eTeachers)) {
            $result = Valid::addErrors([], 'class_teacher', 'class_teacher_is_not_match');
        }
        foreach ($cts as $ct) {
            $orgClassId = empty($ct['class_id']) ? null : $ct['class_id'];
            $sts = ClassTaskModel::checkUserTime($teacherIds, array(ClassUserModel::USER_ROLE_T,ClassUserModel::USER_ROLE_HT), $ct['start_time'], $ct['end_time'], $ct['weekday'], $ct['expire_start_date'], $ct['expire_end_date'], $orgClassId);
            if (!empty($sts)) {
                $result = Valid::addErrors(['data' => ['result' => $sts]], 'class_teacher', 'class_teacher_time_error');
            }
            return $result;
        }
        return true;
    }

    /**
     * @param $where
     * @param $status
     * @return int|null
     */
    public static function updateCUStatus($where,$status){
        return ClassUserModel::updateCUStatus($where,$status);
    }
}