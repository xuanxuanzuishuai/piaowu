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
SUM(duration) AS `sum_duration` FROM " . self::$table . " WHERE `student_id` =:student_id";
        $info = $chdb->fetchOne($sql, ['student_id' => $studentId]);
        return reset($info);
    }

}