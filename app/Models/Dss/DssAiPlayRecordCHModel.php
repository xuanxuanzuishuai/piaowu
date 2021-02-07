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
        //非怀旧模式练琴数据
        $playData = self::getStudentSumByDate($studentId, $startTime, $endTime);
        //怀旧模式练琴数
        $goldenPicturePlayData = self::getStudentSumByDateGoldenPicture($studentId, $startTime, $endTime);
        $playData = array_merge($playData, $goldenPicturePlayData);
        $date = '2021-02-07';
        if ($startTime <= strtotime($date) && $endTime >= strtotime($date)) {
            $playData[] = [
                'sum_duration' => "1200",
                'create_date' => $date,
                'student_id' => $studentId,
                'lesson_id' => 481
            ];
        }
        return $playData;
    }

    /**
     * 学生练琴汇总(按天):非怀旧模式
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        if (is_array($studentId)) {
            $studentId = implode(',', $studentId);
        }
        $chDb = CHDB::getDB();
        $sql = "select sum(duration) as sum_duration,create_date,student_id,lesson_id from (select
                    student_id,
                    duration,
                    create_date,record_id,track_id,lesson_id
                from
                    ".self::$table."
                where
                    duration > 0
                    and track_id !=''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time
                order by
                    duration desc limit 1 by student_id,track_id) as ta group by ta.student_id,ta.create_date,ta.lesson_id";
        return $chDb->queryAll($sql, ['student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
    }

    /**
     * 学生练琴汇总(按天):怀旧模式
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDateGoldenPicture($studentId, $startTime, $endTime)
    {
        if (is_array($studentId)) {
            $studentId = implode(',', $studentId);
        }
        $chDb = CHDB::getDB();
        $sql = "select sum(duration) as sum_duration,create_date,student_id,lesson_id from (select
                    student_id,
                    duration,
                    create_date,record_id,track_id,lesson_id
                from
                    ".self::$table."
                where
                    duration > 0
                    and track_id =''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time) as ta group by ta.student_id,ta.create_date,ta.lesson_id";
        return $chDb->queryAll($sql, ['student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
    }
}