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
use App\Libs\RedisDB;
use App\Libs\Util;

class AIPlayRecordCHModel
{
    static $table = 'ai_play_record';
    //周报统计课程演奏排行榜缓存key
    const WEEK_LESSON_RANK_CACHE_KEY = 'week_lesson_rank_';


    public static function getLessonPlayRank($lessonId, $lessonRankTime)
    {
        $aiPlayInfo = self::getLessonPlayRankList([(int)$lessonId], $lessonRankTime);
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


    /**
     * 获取曲谱排行榜数据
     * @param $lessonIdList
     * @param $lessonRankTime
     * @return array
     */
    public static function getLessonPlayRankList($lessonIdList, $lessonRankTime)
    {
        $chDb = CHDB::getDB();

        $sql = "select tmp.* from (select id as play_id , lesson_id, student_id, record_id as ai_record_id, score_final as score, is_join_ranking from " . self::$table . " AS apr where
                  apr.lesson_id in (:lesson_id)
                  AND apr.ui_entry =:ui_entry
                  AND apr.is_phrase =:is_phrase
                  AND apr.hand =:hand 
                  AND apr.score_final >=:rank_base_score
                  AND apr.end_time >=:start_time
                  AND apr.end_time <:end_time
                order by score_final desc, record_id desc
                limit 1 by lesson_id, student_id) as tmp limit :rank_limit by tmp.lesson_id";

        $map = [
            'lesson_id' => $lessonIdList,
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            'is_phrase' => Constants::STATUS_FALSE,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'rank_base_score' => AIPlayRecordModel::RANK_BASE_SCORE,
            'data_type' => Constants::STATUS_TRUE,
            'rank_limit' => AIPlayRecordModel::RANK_LIMIT,
            'start_time' => $lessonRankTime['start_time'],
            'end_time' => $lessonRankTime['end_time']
        ];
        return $chDb->queryAll($sql, $map);
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
     * 学生练琴汇总(按天):非怀旧模式
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentSumByDate($studentId, $startTime, $endTime)
    {
        $chDb = CHDB::getDB();
        $sql = "select sum(duration) as sum_duration,create_date,student_id from (select
                    student_id,
                    duration,
                    create_date,record_id,track_id
                from
                    ai_peilian_pre.ai_play_record
                where
                    duration > 0
                     and track_id !=''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time
                order by
                    duration desc limit 1 by student_id,track_id) as ta group by ta.student_id,ta.create_date";
        $result = $chDb->queryAll($sql, ['student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
        return $result;
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
        $chDb = CHDB::getDB();
        $sql = "select sum(duration) as sum_duration,create_date,student_id from (select
                    student_id,
                    duration,
                    create_date,record_id,track_id
                from
                    ai_peilian_pre.ai_play_record
                where
                    duration > 0
                     and track_id =''
                    and student_id in (:student_id)
                    and end_time >= :start_time
                    and end_time <= :end_time) as ta group by ta.student_id,ta.create_date";
        $result = $chDb->queryAll($sql, ['student_id' => $studentId, 'start_time' => $startTime, 'end_time' => $endTime]);
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
           count(DISTINCT lesson_id) as lesson_count
        FROM
           {table}
        WHERE
           student_id = {id}
        ", ['table'=>self::$table, 'id' => $student_id]);
    }

    /**
     * 获取一段时间内有练琴记录的学生
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getBetweenTimePlayStudent($startTime, $endTime)
    {
        //获取本周内练琴记录的学生id
        $chDb = CHDB::getDB();
        $sql = "select
                student_id
            from
                ".self::$table."
            where
                end_time >= :start_time
                and end_time <= :end_time
                and duration > 0
            GROUP by
                student_id";
        $map = [
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        return $chDb->queryAll($sql, $map);
    }


    /**
     * 获取用户某段时间内曲目的最高分和最低分相差指定分数的曲目
     * @param $studentIdList
     * @param $startTime
     * @param $endTime
     * @param $diffScore
     * @param $minScoreFinal
     * @return array
     */
    public static function getStudentMaxAndMinScoreByLesson($studentIdList, $startTime, $endTime, $diffScore, $minScoreFinal)
    {
        //获取本周内练琴记录的学生id
        $chDb = CHDB::getDB();
        $sql = "select tmp.*,tmp.max_score-tmp.min_score as score_diff from  (select
                                student_id,lesson_id,MAX(score_final) as max_score,MIN(score_final)  as min_score
                            from
                                ".self::$table."
                            where
                                student_id in (:student_id)
                                and end_time >= :start_time
                                and end_time <= :end_time
                                and ui_entry = :ui_entry
                                and hand = :hand
                                and is_phrase = :is_phrase
                                and duration > 0
                                and data_type = :data_type
                                and score_final > :score_final
                            GROUP by
                                student_id,lesson_id
                                HAVING max_score-min_score> :diff_score) as tmp ORDER BY tmp.student_id asc,
                score_diff desc";
        $map = [
            'student_id' => $studentIdList,
            'start_time' => (int)$startTime,
            'end_time' => (int)$endTime,
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'is_phrase' => Constants::STATUS_FALSE,
            'data_type' => AIPlayRecordModel::DATA_TYPE_NORMAL,
            'diff_score' => (int)$diffScore,
            'score_final' => (int)$minScoreFinal,
        ];
        return $chDb->queryAll($sql, $map);
    }

    /**
     * 设置周报统计时的课程排行榜数据
     * @param $lessonId
     * @param $cacheData
     */
    public static function setWeekLessonRankCache($lessonId, $cacheData)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::WEEK_LESSON_RANK_CACHE_KEY . $lessonId;
        $redis->del([$cacheKey]);
        $redis->zadd($cacheKey, $cacheData);
        $redis->expire($cacheKey, Util::TIMESTAMP_ONEDAY);
    }

    /**
     * 获取周报统计时的课程排行榜数据
     * @param $lessonId
     * @param $studentId
     * @return int
     */
    public static function getWeekLessonRankCache($lessonId, $studentId)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::WEEK_LESSON_RANK_CACHE_KEY . $lessonId;
        return $redis->zrank($cacheKey, $studentId);
    }

    /**
     * 检测周报统计的课程排行榜是否存在
     * @param $lessonId
     * @return int
     */
    public static function checkWeekLessonCacheExists($lessonId)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::WEEK_LESSON_RANK_CACHE_KEY . $lessonId;
        return $redis->exists($cacheKey);
    }

    /**
     * 获取学生演奏曲目最高分曲目
     * @param $studentIdList
     * @param $lessonId
     * @param $lessonRankTime
     * @return array
     */
    public static function getLessonMaxScoreRecordId($studentIdList, $lessonId, $lessonRankTime)
    {
        $chDb = CHDB::getDB();

        $sql = "select
                    lesson_id, student_id, record_id
                from " . self::$table . " AS apr
                where
                apr.student_id in (:student_id)
                  AND apr.lesson_id in (:lesson_id)
                  AND apr.ui_entry =:ui_entry
                  AND apr.is_phrase =:is_phrase
                  AND apr.hand =:hand
                  AND apr.end_time >=:start_time
                  AND apr.end_time <:end_time
                  AND apr.data_type = :data_type
                order by score_final desc, record_id desc
                limit 1 by lesson_id, student_id";

        $map = [
            'student_id' => $studentIdList,
            'lesson_id' => $lessonId,
            'ui_entry' => AIPlayRecordModel::UI_ENTRY_TEST,
            'is_phrase' => Constants::STATUS_FALSE,
            'hand' => AIPlayRecordModel::HAND_BOTH,
            'data_type' => Constants::STATUS_TRUE,
            'start_time' => $lessonRankTime['start_time'],
            'end_time' => $lessonRankTime['end_time']
        ];
        return $chDb->queryAll($sql, $map);
    }

    /**
     * 获取学生某段时间内练习曲目总数
     * @param $studentIdList
     * @param $startTime
     * @param $endTime
     * @return array
     */
    public static function getStudentLessonCountBetweenTime($studentIdList, $startTime, $endTime)
    {
        $chDb = CHDB::getDB();
        $map = [
            'student_id' => $studentIdList,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        $sql = "SELECT
                   count(DISTINCT lesson_id) as lesson_count,student_id
                FROM
                   " . self::$table . "
                WHERE
                   student_id in (:student_id)
                AND end_time >= :start_time
                AND end_time <= :end_time
                group by student_id
                ";
        return $chDb->queryAll($sql, $map);
    }
}