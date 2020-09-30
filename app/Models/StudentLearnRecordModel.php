<?php
/**
 * 用户上课记录
 */
namespace App\Models;

use App\Libs\MysqlDB;

class StudentLearnRecordModel extends Model
{
    public static $table = "student_learn_record";

    const FINISH_LEARNING = 1;//完成上课
    const GO_TO_THE_CLASS= 4; //去上课
    /**
     * 获取用户已完成上课的节数
     * @param $studentId
     * @return array
     */
    public static function getStudentLearnCount($studentId)
    {
        $studentLearnRecord = self::$table;

        $sql = "select COUNT(DISTINCT(lesson_id)) attend_class_count,collection_id from {$studentLearnRecord} where student_id = :student_id and learn_status = :learn_status  group by collection_id";
        $map = [
            ':student_id' => $studentId,
            ':learn_status' => self::FINISH_LEARNING
        ];
        $records = MysqlDB::getDB()->queryAll($sql, $map);
        return $records ?? [];
    }

    /**
     * 获取用户每天的练琴
     * @param $studentId
     * @param $startTime
     * @return array
     */
    public static function getStudentLearnCalendar($studentId, $startTime)
    {
        $studentLearnRecord = self::$table;
        $sql = "SELECT FROM_UNIXTIME(create_time, '%Y%m%d') class_record_time, count(*) class_record_count FROM {$studentLearnRecord} where student_id = :student_id and create_time > :start_time group by class_record_time";
        $map = [
            ':student_id' => $studentId,
            ':start_time' => $startTime
        ];
        $studentBindCourse = MysqlDB::getDB()->queryAll($sql, $map);
        return $studentBindCourse ?? [];
    }

    /**
     * @param $studentId
     * @return array|null
     * 获取学生报名课程已经学习的课程数
     */
    public static function learnNumByCollection($studentId)
    {
        $db = MysqlDB::getDB();
        $sql = "select collection_id,count(1) as learn_num from " . self::$table . " where student_id = " . $studentId . " GROUP BY student_id,collection_id";
        return $db->queryAll($sql);
    }

}