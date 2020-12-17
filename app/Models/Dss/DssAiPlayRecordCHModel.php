<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/14
 * Time: 14:17
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
        $map = [
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        if (is_array($studentId)) {
            $where =" student_id in (".implode(',', $studentId).") ";
        } else {
            $where =" student_id = $studentId ";
        }
        $sql = "
        SELECT
            SUM(duration) as sum_duration,
            count(DISTINCT lesson_id) as lesson_count,
            create_date,
            student_id,
            max(score_final) as score_final
        FROM
            " . self::$table . "
        WHERE
            {$where}
            AND end_time >= :start_time
            AND end_time <= :end_time
            AND duration > 0
        GROUP BY
            student_id,
            create_date";
        return $chDb->queryAll($sql, $map);
    }
}