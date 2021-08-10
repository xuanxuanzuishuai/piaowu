<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-08-10 18:13:39
 * Time: 上午11:49
 */

namespace App\Models\CHModel;


use App\Libs\CHDB;

class AprViewStudentModel extends CHOBModel
{
    /**
     * ai_play_record表 -- student id有序表
     */
    public static $table = "apr_view_student_all";
    
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
        $chDb = CHDB::getBODB();
        $sql = "
            select
                sum(duration) as sum_duration,
                create_date,
                student_id,
                lesson_id
            from
                (
                select
                    student_id,
                    duration,
                    toDate(create_time) AS create_date,
                    record_id,
                    track_id,
                    lesson_id
                from
                    {table}
                where
                    duration > 0
                    and track_id != ''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time
                order by
                    ts desc
                limit 1 by id) as ta
            group by
                student_id,
                create_date,
                lesson_id
        ";
        return $chDb->queryAll($sql, ['table' => self::$table,'student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
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
        $chDb = CHDB::getBODB();
        $sql = "
            select
                sum(duration) as sum_duration,
                create_date,
                student_id,
                lesson_id
            from
                (
                select
                    student_id,
                    duration,
                    toDate(create_time) AS create_date,
                    record_id,
                    track_id,
                    lesson_id
                from
                    {table}
                where
                    duration > 0
                    and track_id = ''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time
                order by
                    ts desc
                limit 1 by id) as ta
            group by
                student_id,
                create_date,
                lesson_id
        ";
        return $chDb->queryAll($sql, ['table' => self::$table,'student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
    }
    
    /**
     * 获取学生练习曲目总数
     * @param $student_id
     * @return array
     */
    public static function getStudentLessonCount($student_id)
    {
        $chdb = CHDB::getBODB();
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
        $chdb = CHDB::getBODB();
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
     * 统计学生练琴天数
     * @param $studentId int | string
     * @param $startTime int | string
     * @param $endTime int | string
     * @return int
     */
    public static function getStudentPlayDayCount($studentId, $startTime = 0, $endTime = 0)
    {
        $chDb = CHDB::getBODB();
        $sql = "
            SELECT
                COUNT(DISTINCT toDate(end_time)) AS play_day
            FROM {table}
            WHERE duration > 0 AND student_id = {$studentId}
        ";
        
        if (!empty($startTime)) {
            $sql .= " AND end_time >= {$startTime}";
        }
        
        if (!empty($endTime)) {
            $sql .= " AND end_time <= {$endTime}";
        }
        
        $record = $chDb->queryAll($sql, ['table' => self::$table])[0];
        return (int)$record['play_day'];
    }
    
    /**
     * @param $studentId
     * 获取学生第一次练琴时间
     */
    public static function getStudentEarliestPlayTime($studentId, $startTime, $endTime)
    {
        $chDB = CHDB::getBODB();
        $sql = "
            SELECT
                student_id,create_time
            FROM
                {table}
            WHERE
                student_id = {$studentId}
                AND duration > 0
                AND create_time >= {$startTime}
                AND create_time <= {$endTime}
            ORDER BY
                create_time ASC
            LIMIT 0,1
        ";
        $res = $chDB->queryAll($sql, ['table' => self::$table]);
        return $res ? $res[0]['create_time'] : 0;
    }
}
