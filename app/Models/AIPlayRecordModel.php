<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 1:54 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

class AIPlayRecordModel extends Model
{
    static $table = 'ai_play_record';

    //排行榜
    const RANK_LIMIT = 150; // 取前n条数据排名
    const RANK_BASE_SCORE = 60; // 大于x分才计入

    /**
     * 学生练琴汇总(按天)
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $sql = "SELECT 
    FROM_UNIXTIME(create_time, '%Y%m%d') AS play_date,
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ai_play_record
WHERE
    student_id = :student_id
        AND create_time >= :start_time
        AND create_time < :end_time
        AND duration > 0
GROUP BY FROM_UNIXTIME(create_time, '%Y%m%d');";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':student_id' => $studentId,
        ];

        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * 个人练琴时长汇总
     * 包含练习模式和上课模式
     * @param $studentId
     * @return array
     */
    public static function getStudentTotalSum($studentId)
    {
        $columns = [
            'lesson_count' => Medoo::raw('COUNT(DISTINCT(lesson_id))'),
            'sum_duration' => Medoo::raw('SUM(duration)'),
        ];

        $where = ['student_id' => $studentId];

        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, $columns, $where);

        return $result;
    }

    /**
     * 曲目排名数据
     * @param $lessonId
     * @return array
     */
    public static function getLessonPlayRank($lessonId)
    {
        $sql = "SELECT
    *
FROM
    (SELECT
        apr.id AS play_id,
            apr.score_final AS score,
            apr.lesson_id,
            apr.student_id,
            apr.record_id AS ai_record_id,
            s.name
    FROM
        ai_play_record AS apr
    LEFT JOIN student s ON apr.student_id = s.id
    WHERE
        apr.lesson_id = :lesson_id
            AND apr.ui_entry = :ui_entry
            AND apr.score_final >= :rank_base_score
    ORDER BY apr.score_final DESC) t
GROUP BY t.student_id
ORDER BY score DESC
LIMIT :rank_limit;";

        $map = [
            ':lesson_id' => $lessonId,
            ':ui_entry' => 2,
            ':rank_base_score' => self::RANK_BASE_SCORE,
            ':rank_limit' => self::RANK_LIMIT,
        ];

        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result ?? [];
    }

    /**
     * 学生曲目最高分
     * 未演奏的返回null
     * @param $studentId
     * @param $lessonId
     * @return array|null
     */
    public static function getStudentLessonBestRecord($studentId, $lessonId)
    {
        $db = MysqlDB::getDB();
        $record = $db->get(self::$table, [
            'id(play_id)',
            'score_final(score)',
            'lesson_id',
            'student_id',
            'record_id(ai_record_id)'
        ], [
            'lesson_id' => $lessonId,
            'student_id' => $studentId,
            'ui_entry' => 2,
            'ORDER' => ['score_final' => 'DESC'],
        ]);

        return $record;
    }
}