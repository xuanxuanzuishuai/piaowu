<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/30
 * Time: 1:54 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use Medoo\Medoo;

class AIPlayRecordModel extends Model
{
    static $table = 'ai_play_record';

    /** app入口 ui_entry */
    const UI_ENTRY_OLD = 1; // 怀旧模式
    const UI_ENTRY_TEST = 2; // 测评
    const UI_ENTRY_LEARN = 3; // 识谱
    const UI_ENTRY_IMPROVE = 4; // 提升
    const UI_ENTRY_CLASS = 5; // 上课模式(5.0以前版本)
    const UI_ENTRY_PRACTICE = 6; // 练习模式(5.0以前版本)

    /** app入口 input_type */
    const INPUT_MIDI = 1; // midi输入
    const INPUT_SOUND = 2; // 声音输入

    /** 演奏模式 practice_mode */
    const PRACTICE_MODE_NORMAL = 1; // 正常
    const PRACTICE_MODE_STEP = 2; // 识谱
    const PRACTICE_MODE_SLOW = 3; // 慢练

    /** 分手 hand */
    const HAND_LEFT = 1; // 左手
    const HAND_RIGHT = 2; // 右手
    const HAND_BOTH = 3; // 双手

    //排行榜
    const RANK_LIMIT = 150; // 取前n条数据排名
    const RANK_BASE_SCORE = 60; // 大于x分才计入

    /**
     * 获取用户日期演奏时长汇总
     * 只统计点评课用户
     * [
     *   ['student_id' => 1, 'play_date' => 20200107', 'sum_duration' => 10],
     *   ...
     * ]
     *
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function studentDailySum($startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $apr = self::$table;
        $s = StudentModel::$table;

        $sql = "SELECT 
    apr.student_id,
    FROM_UNIXTIME(apr.end_time, '%Y%m%d') AS play_date,
    SUM(apr.duration) AS sum_duration
FROM
    {$apr} AS apr
        INNER JOIN
    {$s} AS s ON s.id = apr.student_id
        AND s.`has_review_course` IN (:review_package, :review_plus_package)
WHERE
    apr.end_time > :start_time
        AND apr.end_time <= :end_time
        AND apr.old_format = 0
        AND apr.ui_entry IN (:entry_test, :entry_learn, :entry_improve, :entry_class)
GROUP BY apr.student_id , FROM_UNIXTIME(apr.end_time, '%Y%m%d');";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':review_package' => ReviewCourseModel::REVIEW_COURSE_49,
            ':review_plus_package' => ReviewCourseModel::REVIEW_COURSE_1980,
            ':entry_test' => self::UI_ENTRY_TEST,
            ':entry_learn' => self::UI_ENTRY_LEARN,
            ':entry_improve' => self::UI_ENTRY_IMPROVE,
            ':entry_class' => self::UI_ENTRY_CLASS,
        ];

        return $db->queryAll($sql, $map) ?? [];
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
        $db = MysqlDB::getDB();

        $sql = "SELECT 
    FROM_UNIXTIME(end_time, '%Y%m%d') AS play_date,
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ai_play_record
WHERE
    student_id = :student_id
        AND end_time >= :start_time
        AND end_time < :end_time
        AND duration > 0
GROUP BY FROM_UNIXTIME(end_time, '%Y%m%d');";

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
     * 只包含全曲双手测评
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
            AND apr.is_phrase = :is_phrase
            AND apr.hand = :hand
            AND apr.score_final >= :rank_base_score
    ORDER BY apr.score_final DESC) t
GROUP BY t.student_id
ORDER BY score DESC
LIMIT :rank_limit;";

        $map = [
            ':lesson_id' => $lessonId,
            ':ui_entry' => self::UI_ENTRY_TEST,
            ':is_phrase' => Constants::STATUS_FALSE,
            ':hand' => self::HAND_BOTH,
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
     * 只包含全曲双手测评
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
            'ui_entry' => self::UI_ENTRY_TEST,
            'is_phrase' => Constants::STATUS_FALSE,
            'hand' => self::HAND_BOTH,
            'ORDER' => ['score_final' => 'DESC'],
        ]);

        return $record;
    }
}