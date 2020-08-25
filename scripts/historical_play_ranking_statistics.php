<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Models\AIPlayRecordModel;
use App\Models\HistoryRanksModel;
use App\Models\StudentModel;
use App\Services\AIPlayRecordService;
use Dotenv\Dotenv;
use App\Libs\Util;
use App\Libs\SimpleLogger;
use App\Libs\MysqlDB;
use Exception;

/**
 * 季度排行榜数据
 * 每个季度的第一天的零点执行，统计上一个季度的数据: 0 0 01 *\/3 *  php /data/web/dss_crm_prod/scripts/historical_play_ranking_statistics.php
 * 上线当天凌晨手动执行历史数据的汇总(计划2020-08-24 00:00:00 之前的数据 只执行一次）：php historical_play_ranking_statistics.php
 *
 */
ini_set('max_execution_time', 600);

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
try {
    //当前时间
    $time = time();
    $studentIdJoinRanking = StudentModel::STATUS_JOIN_RANKING_ABLE;
    // 获取上一个季度演奏记录统计的起始时间
    $quarterNum = Util::getUpAndDownQuarter(Util::getQuarterByMonth($time));
    $getLessonRankTime = DictConstants::get(DictConstants::APP_CONFIG_STUDENT, 'get_lesson_rank_time');
    list('start_time' => $startTime, 'end_time' => $endTime) = AIPlayRecordService::getRankTimestamp($getLessonRankTime, Util::getQuarterStartEndTime($quarterNum['up_quarter'])['start_time']);
    if (empty($startTime) && empty($endTime)) {
        SimpleLogger::error("排行榜数据统计起始时间获取失败", []);
        return false;
    }
    //历史数据统计到2020年2季度中，不区分学生is_join_ranking状态
    if ($quarterNum['up_quarter'] == 20202) {
        $studentIdJoinRanking = [StudentModel::STATUS_JOIN_RANKING_DISABLE, StudentModel::STATUS_JOIN_RANKING_ABLE, StudentModel::STATUS_JOIN_RANKING_STOP];
    }
    //获取开启加入排行榜的学生列表
    $studentList = StudentModel::getRecords(
        [
            'status' => StudentModel::SUB_STATUS_NORMAL,
            'is_join_ranking' => $studentIdJoinRanking
        ],
        ['id'],
        false
    );
    if (empty($studentList)) {
        SimpleLogger::error("没有学生参加排行榜", []);
        return false;
    }
    //获取练琴数据
    $sql = "SELECT
                tmp.id AS play_id,
                tmp.score_final AS score,
                tmp.lesson_id,
                tmp.student_id,
                tmp.record_id AS ai_record_id
            FROM
                (SELECT
                    id, lesson_id, student_id, record_id, score_final, is_join_ranking,
                    ROW_NUMBER() OVER(PARTITION BY student_id,lesson_id ORDER BY score_final DESC, id DESC) AS t
                FROM
                    ai_play_record
                WHERE
                        student_id in (:student_ids)
                        AND end_time >= " . $startTime . "
                        AND end_time < " . $endTime . "
                        AND ui_entry = " . AIPlayRecordModel::UI_ENTRY_TEST . "
                        AND is_phrase = " . Constants::STATUS_FALSE . "
                        AND hand = " . AIPlayRecordModel::HAND_BOTH . "
                        AND is_join_ranking = " . Constants::STATUS_TRUE . "
                        AND data_type = " . Constants::STATUS_TRUE . "
                        AND score_final >= " . AIPlayRecordModel::RANK_BASE_SCORE . "

                ) tmp
            WHERE
                tmp.t = 1";
    //分批查询数据:每次查询1000个学生
    $db = MysqlDB::getDB();
    $studentIds = array_column($studentList, 'id');
    $perIdCount = 1000;
    $forTimes = ceil(count($studentIds) / $perIdCount);
    for ($i = 0; $i < $forTimes; $i++) {
        $sids = trim(array_reduce(array_slice($studentIds, $i * $perIdCount, $perIdCount), function ($v1, $v2) {
            return $v1 . "," . $v2;
        }), ',');
        $playRecord = $db->queryAll(str_replace(':student_ids', $sids, $sql));
        if (empty($playRecord)) {
            SimpleLogger::info("排行榜数据为空", []);
            continue;
        }
        //记录数据
        $batchInsertData = [];
        array_map(function ($pv) use (&$batchInsertData, $quarterNum, $time) {
            $batchInsertData[] = [
                'issue_number' => $quarterNum['up_quarter'],
                'lesson_id' => $pv['lesson_id'],
                'student_id' => $pv['student_id'],
                'ai_record_id' => $pv['ai_record_id'],
                'score' => $pv['score'],
                'play_id' => $pv['play_id'],
                'create_time' => $time,
            ];
        }, $playRecord);
        HistoryRanksModel::batchInsert($batchInsertData, false);
    }
} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;