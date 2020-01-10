<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/26
 * Time: 1:56 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

class PlayClassRecordModel extends Model
{
    public static $table = 'play_class_record';

    /**
     * 获取用户日期演奏时长汇总
     * [
     *   ['student_id' => 1, 'play_date' => 20200107', 'sum_duration' => 10],
     *   ...
     * ]
     *
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function studentDailySum($startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $play_class_record = self::$table;

        $sql = "SELECT
    pcr.student_id,
    FROM_UNIXTIME(pcr.create_time, '%Y%m%d') AS play_date,
    SUM(pcr.duration) AS sum_duration
FROM
    {$play_class_record} AS pcr
WHERE
    pcr.create_time BETWEEN :start_time AND :end_time
GROUP BY pcr.student_id, FROM_UNIXTIME(pcr.create_time, '%Y%m%d');";
        $map = [':start_time' => $startTime, ':end_time' => $endTime];

        return $db->queryAll($sql, $map) ?? [];
    }

    /**
     * 演奏数据汇总
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public static function getStudentPlaySum($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, [
            'lesson_count' => Medoo::raw('COUNT(DISTINCT(lesson_id))'),
            'sum_duration' => Medoo::raw('SUM(duration)'),
        ], [
            'student_id' => $studentId,
            'create_time[<>]' => [$startTime, $endTime]
        ]);

        if (empty($result)) {
            return ['lesson_count' => 0, 'sum_duration' => 0];
        }

        $result['sum_duration'] = $result['sum_duration'] ?? 0;

        return $result;
    }

    /**
     * 演奏数据按lesson汇总
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public static function getStudentPlaySumByLesson($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();
        $result = $db->select(self::$table, [
            'lesson_id',
            'sum_duration' => Medoo::raw('SUM(duration)'),
        ], [
            'student_id' => $studentId,
            'create_time[<>]' => [$startTime, $endTime],
            'GROUP' => 'lesson_id',
            'ORDER' => ['lesson_id' => 'DESC']
        ]);

        return $result;
    }
}