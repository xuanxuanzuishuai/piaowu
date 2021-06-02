<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-05-19
 * Time: 16:22:33
 */

/*
 * 端午节限时营销活动
 * http://jira.xiaoyezi.com/browse/AIPL-15492
 */

namespace Script;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\DictConstants;
use App\Libs\File;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\ActivityDuanWuService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$functionName = $_SERVER['argv'][1] ?? '';

switch ($functionName) {
    case 'cacheRefereeRank':
        cacheRefereeRank();
        break;
    case 'statisticsJingDongKa':
        statisticsJingDongKa();
        break;
    case 'statisticsJinYeZi':
        statisticsJinYeZi();
        break;
    default:
        echo 'function not exists' . PHP_EOL;
        break;
}

function getPamars()
{
    $args = $_SERVER['argv'];
    $params = array_slice($args, 2);
    $formatParams = [];
    if ($params) {
        foreach ($params as $param) {
            preg_match('/^--(\w+)=(.+)$/', $param, $match);
            if ($match) {
                $formatParams[$match[1]] = $match[2];
            }
        }
    }
    return $formatParams;
}

/**
 * @param $studentId
 * @return int
 * 获取学生最早学习记录时间
 */
function getStudentEarliestTime($studentId, $startTime, $endTime)
{
    $redis = RedisDB::getConn();
    $cacheKeyEarliestTime = ActivityDuanWuService::$cacheKeyEarliestTime;
    if ($redis->exists($cacheKeyEarliestTime)) {
        $cacheData = $redis->hget($cacheKeyEarliestTime, $studentId);
        if ($cacheData) {
            if ($cacheData >= $startTime && $cacheData <= $endTime) {
                return (int)$cacheData;
            } else {
                $redis->hdel($cacheKeyEarliestTime, $studentId);
            }
        }
        $earliestTime = DssAiPlayRecordCHModel::getStudentEarliestPlayTime($studentId, $startTime, $endTime);
        if ($earliestTime) {
            $redis->hset($cacheKeyEarliestTime, $studentId, $earliestTime);
            return (int)$earliestTime;
        }
    } else {
        $earliestTime = DssAiPlayRecordCHModel::getStudentEarliestPlayTime($studentId, $startTime, $endTime);
        if ($earliestTime) {
            $redis->hset($cacheKeyEarliestTime, $studentId, $earliestTime);
            $second = strtotime('2021-07-01') - time();
            $redis->expire($cacheKeyEarliestTime, $second);
            return (int)$earliestTime;
        }
    }
    return 0;
}

/**
 * 统计排名
 */
function refereeData($startTime, $endTime)
{
    $db = MysqlDB::getDB();
    $maxId = 0;
    $refereeData = [];
    while (true) {
        $sql = "
            SELECT
                id,referee_id,student_id
            FROM
                student_referral_student_statistics
            WHERE
                create_time >= {$startTime}
                AND create_time <= {$endTime} AND last_stage > 0
                AND id > {$maxId}
            ORDER BY id ASC
            LIMIT 0,1000;
        ";
        $res = $db->queryAll($sql);
        if (empty($res)) {
            break;
        }
        $maxId = max(array_column($res, 'id'));
        foreach ($res as $re) {
            $studentId = $re['student_id'];
            $earliestTime = getStudentEarliestTime($studentId, $startTime, $endTime);
            $refereeId = $re['referee_id'];
            if ($earliestTime) {
                if (isset($refereeData[$refereeId])) {
                    $refereeData[$refereeId]['referee_cnt']++;
                    if ($refereeData[$refereeId]['earliest_time'] > $earliestTime) {
                        $refereeData[$refereeId]['earliest_time'] = $earliestTime;
                    }
                } else {
                    $refereeData[$refereeId] = [
                        'referee_id' => $refereeId,
                        'referee_cnt' => 1,
                        'earliest_time' => $earliestTime,
                    ];
                }
            }
        }
    }
    
    $arrCnt = $arrTime = [];
    foreach ($refereeData as $refereeDataE) {
        $arrCnt[] = $refereeDataE['referee_cnt'];
        $arrTime[] = $refereeDataE['earliest_time'];
    }
    
    array_multisort($arrCnt, SORT_DESC, $arrTime, SORT_ASC, $refereeData);
    foreach ($refereeData as $key => $refereeDataE) {
        $refereeData[$key]['rank'] = $key + 1;
    }
    
    return $refereeData;
}

/**
 * 缓存数据
 * 测试脚本 php scripts/activity_duanwu.php cacheRefereeRank --start=2021-06-10 --end=2021-06-20
 * 线上脚本 php scripts/activity_duanwu.php cacheRefereeRank
 */
function cacheRefereeRank()
{
    $startDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_start_time');
    $endDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_end_time');
    $startTime = strtotime($startDate);   //活动开始时间
    $endTime = strtotime($endDate);   //活动结束时间
    
    //如果传了参数,优先使用传参
    $params = getPamars();
    isset($params['start']) && $startTime = strtotime($params['start']);
    isset($params['end']) && $endTime = strtotime($params['end']);
    
    $refereeData = refereeData($startTime, $endTime);
    SimpleLogger::info('cache_referee_rank_data', $refereeData);
    $cacheKeyRefereeRank = ActivityDuanWuService::$cacheKeyRefereeRank;
    $cacheKeyRankCnt = ActivityDuanWuService::$cacheKeyRankCnt;
    $redis = RedisDB::getConn();
    foreach ($refereeData as $refereeDataE) {
        $rank = $refereeDataE['rank'];
        $refereeId = $refereeDataE['referee_id'];
        $refereeCnt = $refereeDataE['referee_cnt'];
        $jsonStr = json_encode($refereeDataE);
        $redis->hset($cacheKeyRefereeRank, $refereeId, $jsonStr);
        $redis->hset($cacheKeyRankCnt, $rank, $refereeCnt);
    }
}

/**
 * 京东卡奖励结果
 * 测试脚本 php scripts/activity_duanwu.php statisticsJingDongKa --start=2021-06-10 --end=2021-06-20
 * 线上脚本 php scripts/activity_duanwu.php statisticsJingDongKa
 */
function statisticsJingDongKa()
{
    $startDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_start_time');
    $endDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_end_time');
    $startTime = strtotime($startDate);   //活动开始时间
    $endTime = strtotime($endDate);   //活动结束时间
    
    //如果传了参数,优先使用传参
    $params = getPamars();
    isset($params['start']) && $startTime = strtotime($params['start']);
    isset($params['end']) && $endTime = strtotime($params['end']);
    
    $refereeData = refereeData($startTime, $endTime);
    SimpleLogger::info('statistics_referee_rank_data', $refereeData);
    $refereeData = array_slice($refereeData, 0, 200);
    $csvData = [];
    $csvData[] = [
        '排名', '用户id', '手机号', '推荐人数', '最早练琴时间'
    ];
    $dbSlave = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    $studentTable = DssStudentModel::getTableNameWithDb();
    foreach ($refereeData as $refereeDatum) {
        $studentId = $refereeDatum['referee_id'];
        $mobileSql = "SELECT mobile from {$studentTable} where id = {$studentId};";
        $mobileRes = $dbSlave->queryAll($mobileSql);
        $mobile = $mobileRes[0]['mobile'] ?? '';
        $csvData[] = [
            $refereeDatum['rank'], $refereeDatum['referee_id'], $mobile, $refereeDatum['referee_cnt'], $refereeDatum['earliest_time'],
        ];
    }
    File::exportFile('京东卡奖励结果排名', $csvData);
}

/**
 * 金叶子奖励结果
 * 测试脚本 php scripts/activity_duanwu.php statisticsJinYeZi --start=2021-06-10 --end=2021-06-20 --end1=2021-07-01 --refund=0
 * 线上脚本 php scripts/activity_duanwu.php statisticsJinYeZi
 */
function statisticsJinYeZi()
{
    $startDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_start_time');
    $endDate = DictConstants::get(DictConstants::ACTIVITY_DUANWU_CONFIG, 'activity_end_time');
    $startTime = strtotime($startDate);   //推荐开始时间
    $endTime = strtotime($endDate);   //推荐结束时间
    $endTime1 = strtotime('2021-07-01');   //开年卡结束时间
    
    //如果传了参数,优先使用传参
    $params = getPamars();
    isset($params['start']) && $startTime = strtotime($params['start']);
    isset($params['end']) && $endTime = strtotime($params['end']);
    isset($params['end1']) && $endTime1 = strtotime($params['end1']);
    
    $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    $maxId = 0;
    $refereeData = [];
    $statisticsTable = StudentReferralStudentStatisticsModel::getTableNameWithDb();
    $detailTable = StudentReferralStudentDetailModel::getTableNameWithDb();
    $studentTable = DssStudentModel::getTableNameWithDb();
    while (true) {
        $sql = "
            SELECT
                a.id,a.referee_id,a.student_id,c.mobile
            FROM
                {$statisticsTable} a
                INNER JOIN {$studentTable} c ON c.id = a.referee_id
            WHERE
                a.id > {$maxId}
                AND a.create_time >= {$startTime}
                AND a.create_time <= {$endTime}
                AND c.has_review_course = 0
                AND EXISTS ( SELECT 1 FROM {$detailTable} b WHERE b.student_id = a.student_id AND b.create_time >= {$startTime} AND b.create_time <= {$endTime1} AND b.stage = 2 )
            ORDER BY id ASC
            LIMIT 0,1000;
        ";
        $res = $db->queryAll($sql);
        if (empty($res)) {
            break;
        }
        $maxId = max(array_column($res, 'id'));
        
        foreach ($res as $re) {
            $refereeId = $re['referee_id'];
            $studentId = $re['student_id'];
            $mobile = $re['mobile'];
            //判断被推荐用户是否退费
            $pres = DssGiftCodeModel::hadPurchasePackageByType($studentId, DssPackageExtModel::PACKAGE_TYPE_NORMAL);
            
            $refund = 1;   //退费
            if ($pres) {
                $refund = 0;
            }
            //如果传了参数,优先使用传参
            isset($params['refund']) && $refund = $params['refund'];
            
            if (!$refund) {   //未退费
                if (isset($refereeData[$refereeId])) {
                    $refereeData[$refereeId]['referee_cnt']++;
                } else {
                    $refereeData[$refereeId] = [
                        'referee_id' => $refereeId,
                        'referee_cnt' => 1,
                        'mobile' => $mobile,
                    ];
                }
            }
        }
    }
    
    $arrCnt = [];
    foreach ($refereeData as $refereeDataE) {
        $arrCnt[] = $refereeDataE['referee_cnt'];
    }
    //根据推荐人数排名
    array_multisort($arrCnt, SORT_DESC, $refereeData);
    foreach ($refereeData as $key => $refereeDataE) {
        $refereeData[$key]['rank'] = $key + 1;
    }
    
    $csvData = [];
    $csvData[] = [
        '排名', '用户id', '手机号', '推荐人数',
    ];
    foreach ($refereeData as $refereeDatum) {
        $csvData[] = [
            $refereeDatum['rank'], $refereeDatum['referee_id'], $refereeDatum['mobile'], $refereeDatum['referee_cnt'],
        ];
    }
    File::exportFile('京东卡奖励结果排名', $csvData);
}
