<?php
/**
 *  用户报名课包
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;

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
     * @param $endTime
     * @return array
     */
    public static function getStudentBindCourse($studentId, $endTime)
    {
        $sql = "select * from student_sign_up where student_id = :student_id and !(first_course_time > :end_time)";
        $map = [
            ':student_id'  => $studentId,
            ':end_time'    => $endTime
        ];

        $studentBindCourse = MysqlDB::getDB()->queryAll($sql, $map);
        return $studentBindCourse ?? [];
    }


    /**
     * 清除用户的日历缓存
     * @param $studentId
     * @param $month
     */
    public static function delStudentMonthRedis($studentId, $month)
    {
        $redis = RedisDB::getConn();

        $cacheKey = StudentSignUpCourseModel::STUDENT_LEARN_MONTH_CALENDAR . $studentId . '_'. $month;
        $redis->del([$cacheKey]);
    }

    /**
     * @param $studentId
     * @param $timestamp
     * @param bool $isRequireSignUp
     * @return array|null
     * 获取用户截止指定时间点当天的报名课程及其上课记录
     */
    public static function getLearnRecords($studentId, $timestamp, $isRequireSignUp = true)
    {
        $signUp = self::$table;
        $learnRecord = StudentLearnRecordModel::$table;
        $student = StudentModel::$table;
        list($beginDay, $endDay) = Util::getStartEndTimestamp($timestamp);
        $weekNo = date("N", $timestamp);

        $where = "s.student_id = " . $studentId . " AND s.start_week = " . $weekNo . "
        AND (NOT (s.first_course_time >" . $endDay . "))";

        if ($isRequireSignUp) {
            $where .= " AND s.bind_status = " . self::COURSE_BING_SUCCESS;
        }

        $sql = "select s.collection_id, s.start_week, s.start_time, s.first_course_time, s.bind_status, s.update_time, l.lesson_id, l.learn_status, stu.sub_end_date from {$signUp} as s 
                inner join {$student} as stu on s.student_id = stu.id 
                left join {$learnRecord} as l on s.collection_id = l.collection_id and s.student_id = l.student_id where " . $where;
        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }

    /**
     * @param $collectionId
     * @param $studentId
     * @return array
     * 获取某个学生指定课程的学习记录
     */
    public static function getLearnRecordByCollection($collectionId, $studentId)
    {
        $signUp = self::$table;
        $learnRecord = StudentLearnRecordModel::$table;
        $student = StudentModel::$table;

        return MysqlDB::getDB()->select("{$student}(s)", [
            "[><]{$signUp}(sin)"    => ["s.id" => "student_id"],
            "[>]{$learnRecord}(lr)" => ["sin.collection_id" => "collection_id", "sin.student_id" => "student_id"],
        ], [
            "sin.collection_id",
            "sin.start_week",
            "sin.start_time",
            "sin.first_course_time",
            "sin.bind_status",
            "sin.update_time",
            "lr.lesson_id",
            "lr.learn_status",
            "s.has_review_course",
            "s.sub_end_date",
        ], [
            "sin.collection_id" => $collectionId,
            "sin.student_id"    => $studentId,
        ]);
    }
}