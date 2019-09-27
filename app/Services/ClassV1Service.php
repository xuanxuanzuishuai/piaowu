<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/9/26
 * Time: 11:32 AM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Valid;
use App\Models\ClassV1Model;
use App\Models\ClassV1UserModel;

class ClassV1Service
{
    /**
     * 添加班级
     * @param $name
     * @param $employeeId
     * @param $studentIds
     * @param $teachers
     * @param $campusId
     * @param $desc
     * @return array|int|mixed|null|string
     */
    public static function addClass($name, $employeeId, $studentIds, $teachers, $campusId, $desc)
    {
        if (!empty($studentIds)) {
            $students = StudentService::getStudentByIds($studentIds);
            if (count($students) != count($studentIds)) {
                return Valid::addErrors([], 'class_student', 'class_student_is_not_match');
            }
        }

        if (!empty($teachers)) {
            $teacherIds = array_keys($teachers);
            $ts = TeacherService::getTeacherByIds($teacherIds);
            if (count($ts) != count($teacherIds)) {
                return Valid::addErrors([], 'class_teacher', 'class_teacher_is_not_match');
            }
        }

        $classId = ClassV1Model::addClass($name, $campusId, $desc, $employeeId);
        if (empty($classId)) {
            return Valid::addErrors([], 'class', 'create_class_error');
        }

        self::addClassUser($classId, $studentIds, $teachers, $employeeId);

        return $classId;
    }

    /**
     * 班级列表
     * @param $page
     * @param $count
     * @param $params
     * @return array
     */
    public static function classList($page, $count, $params)
    {
        list($num, $list) = ClassV1Model::getClassList($page, $count, $params);
        foreach ($list as &$class) {
            $class['class_status'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_STATUS, $class['status']);
        }
        return [$num, $list];
    }

    /**
     * 班级详情
     * @param $classId
     * @return mixed
     */
    public static function getClass($classId)
    {
        $class = ClassV1Model::getRecord(['id' => $classId]);
        $class['class_status'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_STATUS, $class['status']);

        $campus = CampusService::getById($class['campus_id']);
        $class['campus_name'] = $campus['name'];
        $creator = EmployeeService::getById($class['creator_id']);
        $class['creator'] = $creator['name'];
        $creator = EmployeeService::getById($class['operator_id']);
        $class['operator'] = $creator['name'];

        $users = ClassV1UserModel::getClassUserList($classId);
        if (!empty($users)) {
            foreach ($users as $key => $user) {
                $user['role'] = DictService::getKeyValue(Constants::DICT_TYPE_CLASS_USER_ROLE, $user['user_role']);
                if ($user['user_role'] == ClassV1UserModel::ROLE_STUDENT) {
                    $class['students'][] = $user;
                } else {
                    $class['teachers'][] = $user;
                }
            }
        }
        return $class;
    }

    /**
     * 修改班级
     * @param $classId
     * @param $name
     * @param $employeeId
     * @param $studentIds
     * @param $teachers
     * @param $campusId
     * @param $desc
     * @return array|int|mixed|null|string
     */
    public static function modifyClass($classId, $name, $employeeId, $studentIds, $teachers, $campusId, $desc)
    {
        if (!empty($students)) {
            $students = StudentService::getStudentByIds($studentIds);
            if (count($students) != count($studentIds)) {
                return Valid::addErrors([], 'class_student', 'class_student_is_not_match');
            }
        }

        $teacherIds = [];
        if (!empty($teachers)) {
            $teacherIds = array_keys($teachers);
            $ts = TeacherService::getTeacherByIds($teacherIds);
            if (count($ts) != count($teacherIds)) {
                return Valid::addErrors([], 'class_teacher', 'class_teacher_is_not_match');
            }
        }

        ClassV1Model::modifyClass($classId, $name, $campusId, $desc, $employeeId);

        $orgStudentIds = ClassV1UserModel::getClassUsers($classId, ClassV1UserModel::ROLE_STUDENT, 'user_id');
        $orgTeacherIds = ClassV1UserModel::getClassUsers($classId, [ClassV1UserModel::ROLE_TEACHER, ClassV1UserModel::ROLE_T_MANAGER], 'user_id');

        $udiff = function ($a, $b) {
            if ($a == $b) {return 0;} return ($a > $b) ? 1 : -1;
        };

        $delSIds = empty($orgStudentIds) ? [] : array_udiff($orgStudentIds, $studentIds, $udiff);
        $delTIds = empty($orgTeacherIds) ? [] : array_udiff($orgTeacherIds, $teacherIds, $udiff);
        ClassV1UserModel::abandonUser($classId, $delSIds, $delTIds);

        $newSIds = empty($studentIds) ? [] : array_udiff($studentIds, $orgStudentIds, $udiff);
        foreach ($teacherIds as $id) {
            if (in_array($id, $orgTeacherIds)) {
                unset($teachers[$id]);
            }
        }
        self::addClassUser($classId, $newSIds, $teachers, $employeeId);

        return $classId;
    }

    /**
     * 添加学生、老师
     * @param $classId
     * @param $studentIds
     * @param $teachers
     * @param $employeeId
     */
    public static function addClassUser($classId, $studentIds, $teachers, $employeeId)
    {
        $classUser = [];
        $time = time();

        $user = ['class_id' => $classId, 'status' => ClassV1UserModel::STATUS_NORMAL, 'create_time' => $time, 'update_time' => $time, 'operator_id' => $employeeId];
        if (!empty($studentIds)) {
            foreach ($studentIds as $studentId) {
                $classUser[] = array_merge($user, [
                    'user_id' => $studentId,
                    'user_role' => ClassV1UserModel::ROLE_STUDENT
                ]);
            }
        }

        if (!empty($teachers)) {
            foreach ($teachers as $key => $value) {
                $classUser[] = array_merge($user, [
                    'user_id' => $key,
                    'user_role' => $value
                ]);
            }
        }

        if (!empty($classUser)) {
            ClassV1UserModel::batchInsert($classUser, false);
        }
    }
}