<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2020/12/12
 * Time: 10:54 AM
 */

namespace App\Models\Dss;


use App\Libs\CHDB;

class DssAiPlayRecordCHModel
{
    static $table = 'ai_play_record';

    /**
     * 学生某段时间内的练琴记录
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentBetweenTimePlayRecord($studentId, $startTime, $endTime)
    {
        $chDb = CHDB::getDB();
        $sql = "SELECT
                    student_id,
                    create_date,
                    SUM(duration) as sum_duration
                FROM
                    " . self::$table . "
                WHERE
                    student_id =:student_id
                    AND end_time >= :start_time
                    AND end_time <= :end_time
                    AND duration > 0
                GROUP BY
                    student_id,
                    create_date";
        $map = [
            'student_id' => $studentId,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        return $chDb->queryAll($sql, $map);
    }
}