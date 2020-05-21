<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/21
 * Time: 11:17 PM
 */

namespace App\Models;

class StudentCourseManageLogModel extends Model
{
    public static $table = 'student_course_manage_log';
    //操作类型:1手动分配课管
    const OPERATE_TYPE_MANUAL_ALLOT = 1;

    /**
     * 格式化数据
     * @param $students
     * @param $courseManageId
     * @param $employeeId
     * @param $time
     * @param $operateType
     * @return array
     */
    public static function formatAllotCourseManageLogData($students, $courseManageId, $employeeId, $time, $operateType = StudentCourseManageLogModel::OPERATE_TYPE_MANUAL_ALLOT)
    {
        $data = [];
        foreach ($students as $student) {
            $row = [];
            $row['student_id'] = $student['id'];
            $row['old_manage_id'] = $student['course_manage_id'] ?? 0;
            $row['new_manage_id'] = $courseManageId;
            $row['create_time'] = $time;
            $row['operator_id'] = $employeeId;
            $row['operate_type'] = $operateType;
            $data[] = $row;
        }
        return $data;
    }
}