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
use App\Libs\Util;
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


    const DATA_TYPE_NORMAL = 1; // 1 正常评测
    const DATA_TYPE_NOT_EVALUATE = 2; // 2 未进行测评数据 返回或取消
    const DATA_TYPE_EXIT = 3; // 3 非正常退出数据 断电、断网、崩溃、来电、杀掉进程


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

        /*
         * 点评数据包含四种模式的数据
         * UI_ENTRY_TEST
         * UI_ENTRY_LEARN
         * UI_ENTRY_IMPROVE
         * UI_ENTRY_CLASS
         *
         * 其中测评模式 UI_ENTRY_TEST 只记录新版数据(old_format = 0)
         */

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
        AND (
          (apr.ui_entry IN (:entry_learn, :entry_improve, :entry_class))
          OR
          (apr.ui_entry = :entry_test AND old_format = 0)
        )
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

    /**
     * 学生练琴统计
     * @param $params
     * @return array
     */
    public static function recordStatistics($params)
    {
        $where = " WHERE 1 = 1 ";
        $map = [];

        // 统计区间
        $startTime = strtotime($params['play_start_time']);
        $startDate = date("Ymd", $startTime);

        $endTime = strtotime($params['play_end_time']);
        $endDate = date("Ymd", $endTime);

        $order = " ORDER BY s.id DESC ";
        if (!empty($params['student_id'])) {
            $where .= " AND s.id = :student_id ";
            $map[":student_id"] = $params['student_id'];
        } elseif (!empty($params['student_mobile'])) {
            $where .= " AND s.mobile = :student_mobile ";
            $map[":student_mobile"] = $params['student_mobile'];
        } elseif (!empty($params['student_name'])) {
            $where .= " AND s.name like :student_name ";
            $map[":student_name"] = "%" . $params['student_name'] . "%";
        }
        if (isset($params['has_review_course']) && is_numeric($params['has_review_course'])) {
            $where .= " AND s.has_review_course = :has_review_course ";
            $map[":has_review_course"] = $params['has_review_course'];
        }
        // 助教
        if (!empty($params['assistant_id'])) {
            $where .= " AND s.assistant_id = :assistant_id ";
            $map[":assistant_id"] = $params['assistant_id'];
        }
        // 课管
        if (!empty($params['course_manage_id'])) {
            $where .= " AND s.course_manage_id = :course_manage_id ";
            $map[":course_manage_id"] = $params['course_manage_id'];
        }
        // 班级
        if (!empty($params['collection_id'])) {
            $where .= " AND s.collection_id = :collection_id ";
            $map[":collection_id"] = $params['collection_id'];
        }

        // 开班日期
        if (!empty($params['collection_time_start'])) {
            $where .= " AND c.teaching_start_time >= " . strtotime($params['collection_time_start']);
        }
        if (!empty($params['collection_time_end'])) {
            $where .= " AND c.teaching_start_time <= " . strtotime($params['collection_time_end']);
        }

        // 总时长
        if (isset($params['total_duration_min']) && is_numeric($params['total_duration_min'])) {
            $where .= " AND IFNULL(total_duration, 0) >= :total_duration_min ";
            $map[":total_duration_min"] = $params['total_duration_min'] * 60;
        }
        if (isset($params['total_duration_max']) && is_numeric($params['total_duration_max'])) {
            $where .= " AND IFNULL(total_duration, 0) <= :total_duration_max ";
            $map[":total_duration_max"] = $params['total_duration_max'] * 60;
        }

        if (!empty($params['total_duration_sort']) && in_array($params['total_duration_sort'], ['DESC', 'ASC'])) {
            $order = " ORDER BY total_duration " . $params['total_duration_sort'];
        }

        // 日均时长
        if (isset($params['avg_duration_min']) && is_numeric($params['avg_duration_min'])) {
            $where .= " AND IFNULL(avg_duration, 0) >= :avg_duration_min ";
            $map[":avg_duration_min"] = $params['avg_duration_min'] * 60;
        }
        if (isset($params['avg_duration_max']) && is_numeric($params['avg_duration_max'])) {
            $where .= " AND IFNULL(avg_duration, 0) <= :avg_duration_max ";
            $map[":avg_duration_max"] = $params['avg_duration_max'] * 60;
        }

        if (!empty($params['avg_duration_sort']) && in_array($params['avg_duration_sort'], ['DESC', 'ASC'])) {
            $order = " ORDER BY avg_duration " . $params['avg_duration_sort'];
        }

        // 日报数
        if (isset($params['play_days_min']) && is_numeric($params['play_days_min'])) {
            $where .= " AND IFNULL(play_days, 0) >= :play_days_min ";
            $map[":play_days_min"] = $params['play_days_min'];
        }
        if (isset($params['play_days_max']) && is_numeric($params['play_days_max'])) {
            $where .= " AND IFNULL(play_days, 0) <= :play_days_max ";
            $map[":play_days_max"] = $params['play_days_max'];
        }
        if (!empty($params['play_days_sort']) && in_array($params['play_days_sort'], ['DESC', 'ASC'])) {
            $order = " ORDER BY play_days " . $params['play_days_sort'];
        }

        // 点评数
        if (isset($params['review_days_min']) && is_numeric($params['review_days_min'])) {
            $where .= " AND IFNULL(review_days, 0) >= :review_days_min ";
            $map[":review_days_min"] = $params['review_days_min'];
        }
        if (isset($params['review_days_max']) && is_numeric($params['review_days_max'])) {
            $where .= " AND IFNULL(review_days, 0) <= :review_days_max ";
            $map[":review_days_max"] = $params['review_days_max'];
        }
        if (!empty($params['review_days_sort']) && in_array($params['review_days_sort'], ['DESC', 'ASC'])) {
            $order = " ORDER BY review_days " . $params['review_days_sort'];
        }

        $join = "
    LEFT JOIN
    (SELECT
        student_id,
        SUM(duration) total_duration,
        COUNT(DISTINCT FROM_UNIXTIME(end_time, '%Y-%m-%d')) play_days,
        SUM(duration) / COUNT(DISTINCT FROM_UNIXTIME(end_time, '%Y-%m-%d')) avg_duration
    FROM
        " . self::$table . "
    WHERE
        end_time >= " . $startTime . " AND end_time <= " . $endTime . "
    GROUP BY student_id) pr ON pr.student_id = s.id
    LEFT JOIN
    (SELECT
        COUNT(DISTINCT play_date) review_days, student_id
    FROM
        " . ReviewCourseTaskModel::$table . "
    WHERE play_date >= " . $startDate . " AND play_date <= " . $endDate . "
    GROUP BY student_id) rc ON rc.student_id = s.id ";

        $db = MysqlDB::getDB();
        $countSql = "
SELECT COUNT(s.id) count
FROM "  . StudentModel::$table . " s
INNER JOIN " . CollectionModel::$table ." c ON c.id = s.collection_id "
    . $join . $where;
        $queryCount = $db->queryAll($countSql, $map);
        $totalCount = $queryCount[0]['count'];
        if ($totalCount == 0) {
            return [[], 0];
        }

        $sql = "
SELECT
    s.id student_id,
    s.mobile,
    s.name,
    s.has_review_course,
    s.assistant_id,
    s.collection_id,
    s.course_manage_id,
    assist.name assistant_name,
    manager.name manager_name,
    c.name collection_name,
    c.teaching_start_time,
    total_duration,
    play_days,
    avg_duration,
    review_days
FROM " . StudentModel::$table . " s
INNER JOIN " . CollectionModel::$table . " c ON c.id = s.collection_id
LEFT JOIN
    " . EmployeeModel::$table . " assist ON assist.id = s.assistant_id
LEFT JOIN
    " . EmployeeModel::$table . " manager ON manager.id = s.course_manage_id "
. $join;

        $limit = Util::limitation($params['page'], $params['count']);
        $sql = $sql . $where . $order . $limit;

        $records = $db->queryAll($sql, $map);
        return [$records, $totalCount];
    }
}