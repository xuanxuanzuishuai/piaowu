<?php
/**
 *  用户报名课包
 */
namespace App\Models;

use App\Libs\MysqlDB;

class StudentSignUpCourseModel extends Model
{
    public static $table = "student_sign_up";

    const COURSE_NOT_BING_STATUS = 0; //未报名
    const COURSE_BING_STATUS_SUCCESS = 1;//报名成功


    /**
     * 获取当前月学生报名的课程
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentBindCourse($studentId, $startTime, $endTime)
    {
        $sql = "select * from student_sign_up where bind_status = :bind_status and student_id = :student_id and !(last_course_time < :start_time) and !(first_course_time > :end_time)";
        $map = [
            ':bind_status' => self::COURSE_BING_STATUS_SUCCESS,
            ':student_id' => $studentId,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ];

        $studentBindCourse = MysqlDB::getDB()->queryAll($sql, $map);
        return $studentBindCourse ?? [];
    }

}