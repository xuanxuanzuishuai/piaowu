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

    /**
     * @param $studentId
     * @param $timestamp
     * @return array|null
     * 获取用户截止指定时间点当天的报名课程及其上课记录
     */
    public static function getLearnRecords($studentId,$timestamp)
    {
        $signUp = self::$table;
        $learnRecord = StudentLearnRecordModel::$table;
        $student = StudentModel::$table;
        list($beginDay, $endDay) = Util::getStartEndTimestamp($timestamp);
        $weekNo = date("N", $timestamp);

        $where = "s.student_id = " . $studentId . " AND s.start_week = " . $weekNo . " AND s.bind_status = " . self::COURSE_BING_SUCCESS . "
        AND (NOT (s.first_course_time >" . $endDay . ")) AND (NOT (s.last_course_time < " . $beginDay . "))";

        $sql = "select s.collection_id, s.start_week, s.start_time, s.first_course_time, s.bind_status, s.update_time, l.lesson_id, l.sort, l.learn_status, stu.sub_end_date from {$signUp} as s 
                inner join {$student} as stu on s.student_id = stu.id 
                left join {$learnRecord} as l on s.collection_id = l.collection_id and s.student_id = l.student_id where " . $where . "group by s.student_id,s.collection_id";
        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }
}