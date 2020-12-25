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
            SUM(duration) AS sum_duration,
            COUNT(DISTINCT lesson_id) AS lesson_count,
            MAX(score_final) AS score_final,
            create_date,
            student_id 
        FROM (SELECT
                    student_id,
                    duration,
                    create_date,record_id,track_id, lesson_id , score_final 
                FROM
                   ai_play_record apr 
                where
                    {$where}
                    AND duration > 0
                    AND track_id !=''
                    AND end_time >= :start_time
                    AND end_time <= :end_time
                ORDER BY
                    duration desc 
                LIMIT 1 by student_id,track_id
            ) as ta 
            GROUP BY ta.student_id,ta.create_date";
        return $chDb->queryAll($sql, $map);
    }
}