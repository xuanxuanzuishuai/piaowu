<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 1:54 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class AIPlayRecordModel extends Model
{
    static $table = 'ai_play_record';

    /**
     * 学生练琴汇总(按天)
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $sql = "SELECT 
    FROM_UNIXTIME(create_time, '%Y%m%d') AS play_date,
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ai_play_record
WHERE
    student_id = :student_id
        AND create_time >= :start_time
        AND create_time < :end_time
        AND duration > 0
GROUP BY FROM_UNIXTIME(create_time, '%Y%m%d');";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':student_id' => $studentId,
        ];

        $result = $db->queryAll($sql, $map);
        return $result;
    }
}