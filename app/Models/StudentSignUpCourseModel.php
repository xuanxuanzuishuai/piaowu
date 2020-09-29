<?php
/**
 *  用户报名课包
 */
namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class StudentSignUpCourseModel extends Model
{
    public static $table = "student_sign_up";

    //课包报名状态
    const COURSE_BING_ERROR = 0; //未报名
    const COURSE_BING_SUCCESS = 1;//报名成功
    const COURSE_BING_CANCEL = 2;//取消报名

    //课包上课状态
    const COURSE_CLASS_IN = 3; //课包开课中
    const COURSE_CLASS_TO_BE_STARTED = 4;//课包待开课
    const COURSE_CLASS_ABSENTEEISM_STATUS = 5; //缺课
    const COURSE_CLASS_NOT_ABSENTEEISM_STATUS = 6; //没有缺课状态

    const DURATION_30MINUTES = 1800; // 30分钟
    const A_WEEK = 7; //一周

    const STUDENT_LEARN_MONTH_CALENDAR = "student_learn_month_calendar_"; //学生上课月历

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
            ':bind_status' => self::COURSE_BING_SUCCESS,
            ':student_id' => $studentId,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ];

        $studentBindCourse = MysqlDB::getDB()->queryAll($sql, $map);
        return $studentBindCourse ?? [];
    }


    /**
     * 清除用户的日历缓存
     * @param $studentId
     * @param $collectionId
     */
    public static function delStudentMonthRedis($studentId, $collectionId)
    {
        $redis = RedisDB::getConn();

        $collectionData = self::getRecord(['student_id' => $studentId, 'collection_id' => $collectionId]);

        $startMonth = date('m', $collectionData['first_course_time']);
        $endMonth = date('m', $collectionData['last_course_time']);
        for ($i=0; $i <= $endMonth - $startMonth; $i++) {
            $delMonth = $startMonth+$i;
            $cacheKey = self::STUDENT_LEARN_MONTH_CALENDAR . $studentId . '_0' . $delMonth;
            $redis->del([$cacheKey]);
        }
    }
}