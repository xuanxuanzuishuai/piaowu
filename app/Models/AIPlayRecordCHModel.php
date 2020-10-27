<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/8/5
 * Time: 10:54 AM
 */

namespace App\Models;


use App\Libs\CHDB;
use App\Libs\Constants;

class AIPlayRecordCHModel
{
    static $table = 'ai_play_record';

    public static function getLessonPlayRank($lessonId, $lessonRankTime)
    {
        $chdb = CHDB::getDB();

        $sql = "select id as play_id , lesson_id, student_id, record_id as ai_record_id, score_final as score, is_join_ranking from " . self::$table . " AS apr where
  apr.lesson_id =:lesson_id 
  AND apr.ui_entry =:ui_entry 
  AND apr.is_phrase =:is_phrase 
  AND apr.hand =:hand 
  AND apr.score_final >=:rank_base_score 
  AND apr.end_time >=:start_time 
  AND apr.end_time <:end_time  
order by score_final desc, record_id desc 
limit 1 by lesson_id, student_id 
limit :rank_limit";

        $map = [
            'lesson_id' => (int)$lessonId,
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            'is_phrase' => Constants::STATUS_FALSE,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'rank_base_score' => AIPlayRecordModel::RANK_BASE_SCORE,
            'is_join_ranking' => Constants::STATUS_TRUE,
            'data_type' => Constants::STATUS_TRUE,
            'rank_limit' => AIPlayRecordModel::RANK_LIMIT,
            'start_time' => $lessonRankTime['start_time'],
            'end_time' => $lessonRankTime['end_time']
        ];
        $aiPlayInfo = $chdb->queryAll($sql, $map);
        $returnInfo = [];

        if (empty($aiPlayInfo)) {
            return $returnInfo;
        }

        $allStudentId = array_unique(array_column($aiPlayInfo, 'student_id'));
        $students = StudentModel::getRecords([
            'id' => $allStudentId,
            'is_join_ranking' => StudentModel::STATUS_JOIN_RANKING_ABLE
        ], ['id', 'name', 'thumb']);
        $studentInfo = array_column($students, NULL, 'id');

        foreach ($aiPlayInfo as $r) {
            $sid = $r['student_id'];
            if (empty($studentInfo[$sid])) {
                continue;
            }

            $r['name'] = $studentInfo[$sid]['name'];
            $r['thumb'] = $studentInfo[$sid]['thumb'];

            $returnInfo[] = $r;
        }
        return $returnInfo;
    }

    public static function getPlayInfo($studentId)
    {
        $chdb = CHDB::getDB();
        $sql = "SELECT COUNT(DISTINCT(lesson_id)) AS `lesson_count`,
count(distinct(create_date)) as play_day FROM " . self::$table . " WHERE `student_id` =:student_id";
        $info = $chdb->queryAll($sql, ['student_id' => (int)$studentId]);
        return reset($info);
    }

    /**
     * 学生练琴汇总(按天)
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        $chdb = CHDB::getDB();

        $sql = "select formatDateTime(parseDateTimeBestEffort(toString(create_time)), '%Y%m%d') AS play_date,
COUNT(DISTINCT lesson_id) AS lesson_count,SUM(duration) AS sum_duration from (
select * from ai_peilian.ai_play_record where student_id =:student_id and duration > 0 and end_time >=:start_time and end_time <:end_time
order by duration desc limit 1 by track_id)
GROUP BY play_date";
        $result = $chdb->queryAll($sql, ['student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
        return $result;
    }

    /**
     * @param $startTime
     * @param $endTime
     * @return array
     * 时间范围内多少用户练琴
     */
    public static function getStudentPlayNum($startTime, $endTime)
    {
        $chdb = CHDB::getDB();

        $sql = "SELECT count(distinct student_id) play_user_num
FROM
" . self::$table . "
WHERE
end_time >=:start_time
AND end_time <:end_time";
        $result = $chdb->queryAll($sql, ['start_time' => $startTime, 'end_time' => $endTime]);
        return reset($result);
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
     * 获取学生练习曲目总数
     * @param $student_id
     * @return array
     */
    public static function getStudentLessonCount($student_id)
    {
        $chdb = CHDB::getDB();
        return $chdb->queryAll("
        SELECT
           count(DISTINCT lesson_id)
        FROM
           {table}
        WHERE
           student_id = {id}
        ", ['table'=>self::$table, 'id' => $student_id]);
    }
}