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
                    duration desc,create_date desc limit 1 by student_id,track_id) as ta group by ta.student_id,ta.create_date,ta.lesson_id";
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

    /**
     * 获取学生练习曲目总数
     * @param $student_id
     * @return array
     */
    public static function getStudentLessonCount($student_id)
    {
        $chdb = CHDB::getDB();
        return $chdb->queryAll("
        SELECT
           count(DISTINCT lesson_id) as lesson_count
        FROM
           {table}
        WHERE
           student_id = {id}
        ", ['table'=>self::$table, 'id' => $student_id]);
    }

    /**
     * 获取用户最高分和最低分
     * @param $student_id
     * @return array
     */
    public static function getStudentMaxAndMinScore($student_id)
    {
        $chdb = CHDB::getDB();
        $sql  = "
        SELECT
           Max(score_final) AS max_score,
           Min(score_final) AS min_score
        FROM
           {table}
        WHERE
           student_id = {id}
           AND score_final > 0
        GROUP BY
           lesson_id
        ";
        $result = $chdb->queryAll($sql, ['table' => self::$table, 'id' => $student_id]);
        return $result;
    }

    /**
     * @param $recordId
     * @return array|mixed
     * 当前演奏详情数据
     */
    public static function getRecordIdInfo($recordId)
    {
        $chdb = CHDB::getDB();
        $sql  = "
        SELECT
           input_type,
           audio_url,
           student_id,
           score_rank,
           score_final,
           score_complete,
           score_pitch,
           score_rhythm,
           score_speed,
           score_speed_average,
           score_rank,
           lesson_id,
           record_id
        FROM
           {table}
        WHERE
           record_id = {id}
           order by score_final desc limit 1
        ";
        $result = $chdb->queryAll($sql, ['table' => self::$table, 'id' => $recordId]);
        return empty($result) ? [] : $result[0];
    }

    /**
     * 统计学生练琴天数
     * @param $studentId int | string
     * @param $startTime int | string
     * @param $endTime int | string
     * @return int
     */
    public static function getStudentPlayDayCount($studentId, $startTime = 0, $endTime = 0)
    {
        $chDb = new CHDB();
        $sql = "SELECT 
                COUNT(DISTINCT toDate(end_time)) AS play_day 
                FROM " . self::$table . "
                WHERE duration > 0 
                  AND student_id = {$studentId}";

        if (!empty($startTime)) {
            $sql .= " and end_time >= {$startTime}";
        }

        if (!empty($endTime)) {
            $sql .= " and end_time <= {$endTime}";
        }

        $record = $chDb->queryAll($sql)[0];
        return (int)$record['play_day'];
    }
    
    /**
     * @param $studentId
     * 获取学生第一次练琴时间
     */
    public static function getStudentEarliestPlayTime($studentId, $startTime, $endTime)
    {
        $chDB = CHDB::getDB();
        $sql = "
            SELECT
                student_id,create_time
            FROM
                ai_play_record
            WHERE
                student_id = {$studentId}
                AND duration > 0
                AND create_time >= {$startTime}
                AND create_time <= {$endTime}
            ORDER BY
                create_time ASC
            LIMIT 0,1
        ";
        $res = $chDB->queryAll($sql);
        return $res ? $res[0]['create_time'] : 0;
    }
}
