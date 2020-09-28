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
     * 获取万圣节期间学生练琴时长汇总
     * @param $eventId
     * @param $everyDayValidSeconds
     * @param $startTime
     * @param $endTime
     * @param $rankLimit
     * @param $studentId
     * @return array
     */
    public static function getHalloweenDurationRank($eventId,$everyDayValidSeconds, $startTime, $endTime, $rankLimit)
    {
        $chDb = CHDB::getDB();
        $whereData = [
            'every_day_valid_seconds' => (int)$everyDayValidSeconds,
            'event_id' => $eventId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'rank_limit' => (int)$rankLimit,
            'status' => ActivitySignUpModel::STATUS_ABLE,
        ];
        $sql = "select
                    t.student_id,
                    if(sum(t.user_duration)>= :every_day_valid_seconds,
                    :every_day_valid_seconds,
                    sum(t.user_duration)) as user_total_du,
                    max(t.complete_time) as comt
                from
                    (
                    SELECT
                        apr.student_id, apr.create_time, asu.complete_time, apr.end_time as aet, if (apr.create_time >= asu.create_time,
                        apr.duration ,
                        0) as user_duration
                    FROM
                        ai_peilian.ai_play_record AS apr
                    inner JOIN ai_peilian.activity_sign_up AS asu ON
                        (asu.user_id = apr.student_id )
                    WHERE
                        asu.`status` = :status
                        and asu.event_id = :event_id
                        and apr.duration >0
                        and (aet BETWEEN :start_time and :end_time) ) as t
                GROUP by
                    t.student_id
                order by
                    user_total_du desc,
                    comt asc
                limit :rank_limit";
        $result = $chDb->queryAll($sql, $whereData);
        return $result;
    }

    /**
     * 获取用户某段时间内每天的有效练琴时长
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @param $everyDayValidSeconds
     * @return array
     */
    public static function getStudentValidTotalDuration($studentId, $startTime, $endTime, $everyDayValidSeconds)
    {
        $chDb = CHDB::getDB();
        $sql = "select
                    create_date,
                    if(SUM(duration)>=:valid_seconds,:valid_seconds,SUM(duration)) AS sum_duration
	            from
                    ai_peilian.ai_play_record
                where
                    student_id = :student_id
                    and duration > 0
                    and end_time >= :start_time
                    and end_time <= :end_time
                GROUP by create_date ";
        $whereData = [
            'student_id' => $studentId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'valid_seconds' => (int)$everyDayValidSeconds,
        ];
        $result = $chDb->queryAll($sql, $whereData);
        return $result;
    }

}