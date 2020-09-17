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
use App\Libs\RedisDB;
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
    const RANK_SCORE_FINAL = 90; //大于90分才会返回精彩演奏
    const GET_DAY_WONDERFUL_DATA_LIMIT = 3; //日报精彩演出，最多返回3条数据

    const DATA_TYPE_NORMAL = 1; // 1 正常评测
    const DATA_TYPE_NOT_EVALUATE = 2; // 2 未进行测评数据 返回或取消
    const DATA_TYPE_EXIT = 3; // 3 非正常退出数据 断电、断网、崩溃、来电、杀掉进程

    //当日学生练琴总时长有序集合缓存key
    const STUDENT_DAILY_DURATIONS = 'students_daily_durations_';

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

        $sql = "SELECT 
    apr.student_id,
    SUM(apr.duration) AS sum_duration
FROM
    {$apr} AS apr
WHERE
    apr.end_time >= :start_time
        AND apr.end_time <= :end_time
GROUP BY apr.student_id";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
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
            'play_day' => Medoo::raw("COUNT(DISTINCT FROM_UNIXTIME(end_time, '%y-%m-%d'))")
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
    public static function getLessonPlayRank($lessonId, $lessonRankTime)
    {
        $sql = "SELECT
    tmp.id AS play_id,
    tmp.score_final AS score,
    tmp.lesson_id,
    tmp.student_id,
    tmp.record_id AS ai_record_id,
    s.name,
    s.thumb
FROM
    (SELECT 
        id, lesson_id, student_id, record_id, score_final, is_join_ranking,
        ROW_NUMBER() OVER(PARTITION BY student_id ORDER BY score_final DESC, id DESC) AS t
    FROM
        ai_play_record AS apr
    WHERE
        apr.lesson_id = :lesson_id
            AND apr.ui_entry = :ui_entry
            AND apr.is_phrase = :is_phrase
            AND apr.hand = :hand
            AND apr.is_join_ranking = :is_join_ranking
            AND apr.data_type = :data_type
            AND apr.score_final >= :rank_base_score
            AND apr.end_time >= :start_time
            AND apr.end_time < :end_time
    ) tmp
    INNER JOIN
        student AS s ON tmp.student_id = s.id and tmp.is_join_ranking = s.is_join_ranking
WHERE
    tmp.t = 1
ORDER BY tmp.score_final DESC , tmp.id
LIMIT :rank_limit;";

        $map = [
            ':lesson_id' => $lessonId,
            ':ui_entry' => self::UI_ENTRY_TEST,
            ':is_phrase' => Constants::STATUS_FALSE,
            ':hand' => self::HAND_BOTH,
            ':rank_base_score' => self::RANK_BASE_SCORE,
            ':is_join_ranking' => Constants::STATUS_TRUE,
            ':data_type' => Constants::STATUS_TRUE,
            ':rank_limit' => self::RANK_LIMIT,
            ':start_time' => $lessonRankTime['start_time'],
            ':end_time' => $lessonRankTime['end_time']
        ];

        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result ?? [];
    }

    /**
     * 上榜时间段内获取学生曲目最高分
     * 未演奏的返回null
     * 只包含全曲双手测评
     * @param $studentId
     * @param $lessonId
     * @param $lessonRankTime
     * @return array|null
     */
    public static function getStudentLessonBestRecord($studentId, $lessonId, $lessonRankTime)
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
            'is_join_ranking' => Constants::STATUS_TRUE,
            'end_time[>=]' => $lessonRankTime['start_time'],
            'end_time[<]' => $lessonRankTime['end_time'],
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
        $where = " WHERE 1 = 1 and (s.has_review_course != :has_review_course or s.collection_id != :collection_id)";
        $map = [":has_review_course" => ReviewCourseModel::REVIEW_COURSE_NO, ":collection_id" => Constants::STATUS_FALSE];

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
            $where .= " AND IFNULL(play_days, 0) >= :review_days_min ";
            $map[":review_days_min"] = $params['review_days_min'];
        }
        if (isset($params['review_days_max']) && is_numeric($params['review_days_max'])) {
            $where .= " AND IFNULL(play_days, 0) <= :review_days_max ";
            $map[":review_days_max"] = $params['review_days_max'];
        }
        if (!empty($params['review_days_sort']) && in_array($params['review_days_sort'], ['DESC', 'ASC'])) {
            $order = " ORDER BY play_days " . $params['review_days_sort'];
        }

        $join = "
    LEFT JOIN
    (SELECT
        student_id,
        SUM(sum_duration) total_duration,
        COUNT(DISTINCT play_date) play_days,
        SUM(sum_duration) / COUNT(DISTINCT play_date) avg_duration
    FROM
        " . ReviewCourseTaskModel::$table . "
    WHERE play_date >= " . $startDate . " AND play_date <= " . $endDate . "
    GROUP BY student_id) rc ON rc.student_id = s.id ";

        $db = MysqlDB::getDB();
        $countSql = "
SELECT COUNT(s.id) count
FROM "  . StudentModel::$table . " s
LEFT JOIN " . CollectionModel::$table ." c ON c.id = s.collection_id "
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
    avg_duration
FROM " . StudentModel::$table . " s
LEFT JOIN " . CollectionModel::$table . " c ON c.id = s.collection_id
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

    /**
     * 练琴统计（今日数据查询）
     * @param $mobile
     * @param $startDate
     * @return array
     */
    public static function todayRecordStatistics($mobile, $startDate)
    {
        $where = " WHERE s.mobile = :mobile";
        $map = [":mobile" => $mobile];
        $join = " LEFT JOIN (SELECT student_id, SUM(duration) total_duration FROM " . self::$table . " WHERE end_time >= " . $startDate . " GROUP BY student_id) rc ON rc.student_id = s.id ";

        $sql = "SELECT
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
    total_duration
FROM " . StudentModel::$table . " s LEFT JOIN " . CollectionModel::$table . " c ON c.id = s.collection_id LEFT JOIN " . EmployeeModel::$table . " assist ON assist.id = s.assistant_id LEFT JOIN " . EmployeeModel::$table . " manager ON manager.id = s.course_manage_id " . $join;

        $limit = " limit 1";
        $sql = $sql . $where . $limit;
        $db = MysqlDB::getDB();
        $records = $db->queryAll($sql, $map);
        return $records ?? [];
    }

    /**
     * 添加数据记录
     * @param $studentId
     * @param $recordData
     * @param $stepDuration
     * @return int|mixed|null|string
     */
    public static function addRecord($studentId, $recordData, $stepDuration)
    {
        $recordId = self::insertRecord($recordData, false);
        if (!empty($recordId) && !empty($stepDuration)) {
            self::setDailyDurationCache($studentId, $stepDuration);
        }
        return $recordId;
    }

    /**
     * 修改数据记录
     * @param $studentId
     * @param $playRecordId
     * @param $recordData
     * @param $stepDuration
     * @return int|mixed|null|string
     */
    public static function modifyRecord($studentId, $playRecordId, $recordData, $stepDuration)
    {
        $recordId = AIPlayRecordModel::updateRecord($playRecordId, $recordData);
        if (!empty($recordId) && !empty($stepDuration)) {
            self::setDailyDurationCache($studentId, $stepDuration);
        }
        return $recordId;
    }

    /**
     * 设置学生每日练琴缓存
     * @param $stepDuration
     * @param $studentId
     */
    private static function setDailyDurationCache($studentId, $stepDuration = 0)
    {
        $redis = RedisDB::getConn();
        $time = time();
        $date = date("Ymd", $time);
        $expireTime = 0;
        $cacheKey = self::STUDENT_DAILY_DURATIONS . $date;
        if ($redis->zrank($cacheKey, $studentId) === null) {
            //初始化学生当日练琴数据，避免数据缺失
            $dayStartEndTimestamp = Util::getStartEndTimestamp($time);
            $playRecord = self::getStudentSumByDate($studentId, $dayStartEndTimestamp[0], $dayStartEndTimestamp[1]);
            if (!empty($playRecord)) {
                $stepDuration = array_sum(array_column($playRecord, 'sum_duration'));
            } else {
                $stepDuration = 0;
            }
            $expireTime = strtotime("36 hours", $dayStartEndTimestamp[0]) - $time;
        }
        //设置分值和有效期
        $redis->zincrby($cacheKey, $stepDuration, $studentId);
        if (!empty($expireTime)) {
            $redis->expire($cacheKey, $expireTime);
        }
    }

    /**
     * 获取学生每日练琴缓存
     * @param $studentId
     * @return float|int
     */
    public static function getDailyDurationCache($studentId)
    {
        $redis = RedisDB::getConn();
        $date = date("Ymd");
        $cacheKey = self::STUDENT_DAILY_DURATIONS . $date;
        if ($redis->zrank($cacheKey, $studentId) === null) {
            //缓存不存在，查询数据库进行初始化
            self::setDailyDurationCache($studentId);
        }
        $totalDuration = $redis->zscore($cacheKey, $studentId);
        return $totalDuration;
    }

    /**
     * 获取有演奏的学生信息
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getPlayedStudentInfo($startTime, $endTime)
    {
        $db = MysqlDB::getDB();

        $sql = "SELECT
    DISTINCT apr.student_id,
    uw.open_id
FROM
    ai_play_record AS apr
        INNER JOIN
    user_weixin AS uw ON apr.student_id = uw.user_id
        AND uw.app_id = 8
        AND uw.user_type = 1
        AND uw.busi_type = 1
        AND uw.status = 1
WHERE
    apr.end_time >= :start_time
        AND apr.end_time < :end_time
        AND apr.duration > 0;";

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
        ];

        $studentInfo = $db->queryAll($sql, $map);

        return $studentInfo ?? [];
    }

    /**
     * 根据日期获取学生练琴天数
     * @param $studentIds
     * @param $startTime
     * @param $endTime
     * @return array|null
     */
    public static function getStudentPlayCountByDate($studentIds, $startTime, $endTime)
    {
        $sql = "SELECT DISTINCT `student_id`,
                count(*) OVER (PARTITION BY `student_id`) num
                FROM
                  (SELECT `student_id`,
                          from_unixtime(`end_time`, '%Y-%m-%d') `date`
                   FROM `ai_play_record`
                   WHERE `student_id` IN (".implode(',', $studentIds).")
                     AND `end_time` >= :start_time
                     AND `end_time` <= :end_time
                     AND `duration` > 0
                   GROUP BY `student_id`, `date`) tmp";

        $map = [
            ":start_time" => $startTime,
            ":end_time" => $endTime
        ];
        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    /**
     * 获取累计练习曲目
     * @param $studentId
     * @return int
     */
    public static function getAccumulateLessonCount($studentId)
    {
        $sql = 'select count(*) as count from(
                select *, ROW_NUMBER() OVER(PARTITION BY lesson_id ) n from ai_play_record where student_id = :student_id) ai where n = 1';
        $map = [
            ':student_id' => $studentId,
        ];
        $accumulateLessonCount = MysqlDB::getDB()->queryAll($sql, $map);
        return !empty($accumulateLessonCount[0]['count']) ? (INT)$accumulateLessonCount[0]['count'] : 0;

    }

    /**
     * 获取累计练琴天数
     * @param $studentId
     * @return int
     */
    public static function getAccumulateDays($studentId)
    {
        $db = MysqlDB::getDB();
        $countCol = Medoo::raw("COUNT(DISTINCT FROM_UNIXTIME(end_time, '%y-%m-%d'))");
        $countResult = $db->get(self::$table, ['count' => $countCol], ['student_id' => $studentId]);
        return !empty($countResult['count']) ? (INT)$countResult['count'] : 0;
    }

    /**
     * 获取当天精彩演奏，每天有过全曲评测的曲目且最高分有超过90的，按照最高分倒序，给出3首曲目的当天最高分演奏
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getDayWonderfulData($studentId, $startTime, $endTime)
    {
        $sql = 'select ai.id, ai.student_id, ai.lesson_id, ai.score_id, ai.record_id, ai.phrase_id, ai.hand, ai.ui_entry, ai.duration, ai.audio_url, ai.score_final from
                (select apr.*, ROW_NUMBER() OVER(PARTITION BY apr.lesson_id ORDER BY apr.score_final DESC) n from ai_play_record apr where apr.student_id = :student_id and apr.ui_entry = :ui_entry and apr.hand = :hand
                and apr.is_phrase = :is_phrase and apr.score_final >= :score_final and apr.end_time >= :start_time and apr.end_time < :end_time) ai where ai.n = 1 order by ai.score_final desc limit :limit';

        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':student_id' => $studentId,
            ':ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            ':hand' => AIPlayRecordModel::HAND_BOTH,
            ':is_phrase' => Constants::STATUS_FALSE,
            ':score_final' => AIPlayRecordModel::RANK_SCORE_FINAL,
            ':limit' => self::GET_DAY_WONDERFUL_DATA_LIMIT
        ];
        $dayWonderfulData = MysqlDB::getDB()->queryAll($sql, $map);
        return $dayWonderfulData ?? [];

    }
}