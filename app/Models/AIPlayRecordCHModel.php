<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/8/5
 * Time: 10:54 AM
 */

namespace App\Models;


use App\Libs\CHDB;

class AIPlayRecordCHModel
{
    static $table = 'ai_play_record';

    public static function testLessonRank($lessonId)
    {
        $chdb = CHDB::getDB();

        $sql = "select
  id,
  lesson_id,
  student_id,
  score_final,
  record_id
from
  {apr_table} AS apr
where
  apr.lesson_id = {lesson_id}
  AND apr.ui_entry = 2
  AND apr.is_phrase = 0
  AND apr.hand = 3
  AND apr.score_final >= 60
order by
  score_final desc,
  record_id desc
limit
  1 by lesson_id, student_id
limit {rank_limit}";

        $map = [
            'apr_table' => self::$table,
            'lesson_id' => $lessonId,
            'rank_limit' => 150,
        ];

        return $chdb->queryAll($sql, $map);
    }

    public static function getPlayInfo($studentId)
    {
        $chdb = CHDB::getDB();
        $sql = "SELECT COUNT(DISTINCT(lesson_id)) AS `lesson_count`,
count(distinct(formatDateTime(parseDateTimeBestEffort(toString(create_time)), '%Y%m%d'))) as play_day FROM " . self::$table . " WHERE `student_id` =:student_id";
        $info = $chdb->queryAll($sql, ['student_id' => $studentId]);
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
order by record_id desc limit 1 by track_id)
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

}