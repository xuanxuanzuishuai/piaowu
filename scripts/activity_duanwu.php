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
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
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
 * 获取学生最早学习记录时间
 * @param $studentId
 * @param $startTime
 * @param $endTime
 * @return int
 */
function getStudentEarliestTime($studentId, $startTime, $endTime)
{
    $redis = RedisDB::getConn();
    $cacheKeyEarliestTime = ActivityDuanWuService::$cacheKeyEarliestTime;
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
    return 0;
}

/**
 * 统计排名
 * @param $startTime
 * @param $endTime
 * @return array
 */
function refereeData($startTime, $endTime)
{
    $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    $maxId = 0;
    $refereeData = [];
    $statisticsTable = StudentReferralStudentStatisticsModel::getTableNameWithDb();
    $studentTable = DssStudentModel::getTableNameWithDb();
    $startDate = date('Ymd', $startTime);
    $endDate = date('Ymd', $endTime);
    while (true) {
        $sql = "
            SELECT
                a.id,a.referee_id,a.student_id,b.mobile,b.has_review_course,b.sub_start_date,b.sub_end_date
            FROM
                {$statisticsTable} a
                INNER JOIN {$studentTable} b ON b.id = a.referee_id
            WHERE
                a.id > {$maxId}
                AND a.create_time >= {$startTime}
                AND a.create_time <= {$endTime}
            ORDER BY a.id ASC
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
            $mobile = $re['mobile'];
            $validYear = 0;   //判断年卡用户
            if ($re['has_review_course'] == 2 && $re['sub_start_date'] <= $endDate && $re['sub_end_date'] >= $startDate) {
                $validYear = 1;
            }
            if ($earliestTime && $validYear) {
                if (isset($refereeData[$refereeId])) {
                    $refereeData[$refereeId]['referee_cnt']++;
                    if ($refereeData[$refereeId]['earliest_time'] > $earliestTime) {
                        $refereeData[$refereeId]['earliest_time'] = $earliestTime;
                    }
                } else {
                    $refereeData[$refereeId] = [
                        'referee_id' => $refereeId,
                        'mobile' => $mobile,
                        'referee_cnt' => 1,
                        'earliest_time' => $earliestTime,
                    ];
                }
            }
        }
    }
    
    //设置 "学生最早练琴时间缓存" 过期时间
    $cacheKeyEarliestTime = ActivityDuanWuService::$cacheKeyEarliestTime;
    $second = strtotime('2021-07-01') - time();
    $redis = RedisDB::getConn();
    $second >0 && $redis->expire($cacheKeyEarliestTime, $second);
    
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
    $keepTime = 180;   //最小保持时间3分钟
    $oldExpire = 300;   //老数据过期时间
    
    //如果传了参数,优先使用传参
    $params = getPamars();
    isset($params['start']) && $startTime = strtotime($params['start']);
    isset($params['end']) && $endTime = strtotime($params['end']);
    isset($params['keep_time']) && $keepTime = strtotime($params['keep_time']);
    isset($params['old_expire']) && $oldExpire = strtotime($params['old_expire']);
    
    $refereeData = refereeData($startTime, $endTime);
    SimpleLogger::info('cache_referee_rank_data', $refereeData);
    $redis = RedisDB::getConn();
    $time = time();
    $cacheKeyRankKeys = ActivityDuanWuService::$cacheKeyRefereeRankKeys;
    $keysStr = $redis->get($cacheKeyRankKeys);
    if ($keysStr) {
        $keysArr = json_decode($keysStr, true);
        $lastStartTime = $keysArr['last_start_time'];
        $lastEndTime = $keysArr['last_end_time'];
        $lastTime = $keysArr['last_time'];
        $cacheKeyRefereeRankOld = ActivityDuanWuService::$cacheKeyRefereeRank . '_' . $lastStartTime . '_' . $lastEndTime . '_' . $lastTime;
        $cacheKeyRankCntOld = ActivityDuanWuService::$cacheKeyRankCnt . '_' . $lastStartTime . '_' . $lastEndTime . '_' . $lastTime;
        $cacheKeyRefereeRankNew = '';
        $cacheKeyRankCntNew = '';
        if ($time - $lastTime > $keepTime || $startTime != $lastStartTime || $endTime != $lastEndTime) {   //缓存大于保持时间,或者不同开始结束时间,更新缓存key
            $cacheKeyRefereeRankNew = ActivityDuanWuService::$cacheKeyRefereeRank . '_' . $startTime . '_' . $endTime . '_' . $time;
            $cacheKeyRankCntNew = ActivityDuanWuService::$cacheKeyRankCnt . '_' . $startTime . '_' . $endTime . '_' . $time;
            $keysStr = json_encode(['last_start_time' => $startTime, 'last_end_time' => $endTime, 'last_time' => $time]);
        }
    } else {
        $cacheKeyRefereeRankOld = ActivityDuanWuService::$cacheKeyRefereeRank;
        $cacheKeyRankCntOld = ActivityDuanWuService::$cacheKeyRankCnt;
        $cacheKeyRefereeRankNew = ActivityDuanWuService::$cacheKeyRefereeRank . '_' . $startTime . '_' . $endTime . '_' . $time;
        $cacheKeyRankCntNew = ActivityDuanWuService::$cacheKeyRankCnt . '_' . $startTime . '_' . $endTime . '_' . $time;
        $keysStr = json_encode(['last_start_time' => $startTime, 'last_end_time' => $endTime, 'last_time' => $time]);
    }
    //如果换新的缓存key,数据存入新key
    $cacheKeyRefereeRank = $cacheKeyRefereeRankNew ? $cacheKeyRefereeRankNew : $cacheKeyRefereeRankOld;
    $cacheKeyRankCnt = $cacheKeyRankCntNew ? $cacheKeyRankCntNew : $cacheKeyRankCntOld;
    foreach ($refereeData as $refereeDataE) {
        $rank = $refereeDataE['rank'];
        $refereeId = $refereeDataE['referee_id'];
        $refereeCnt = $refereeDataE['referee_cnt'];
        $jsonStr = json_encode($refereeDataE);
        $redis->hset($cacheKeyRefereeRank, $refereeId, $jsonStr);
        $redis->hset($cacheKeyRankCnt, $rank, $refereeCnt);
    }
    
    $second = strtotime('2021-07-01') - time();
    if ($cacheKeyRefereeRankNew && $cacheKeyRankCntNew) {
        //新版本缓存过期时间更新为2021-07-01过期
        $redis->expire($cacheKeyRefereeRankNew, $second);
        $redis->expire($cacheKeyRankCntNew, $second);
        //老版本缓存过期时间更新为5分钟后过期
        $redis->expire($cacheKeyRefereeRankOld, $oldExpire);
        $redis->expire($cacheKeyRankCntOld, $oldExpire);
        //更新缓存key
        $redis->set($cacheKeyRankKeys, $keysStr);
        $redis->expire($cacheKeyRankKeys, $second);
    }
}

/**
 * 京东卡奖励结果
 * 测试脚本 php scripts/activity_duanwu.php statisticsJingDongKa --start=2021-06-10 --end=2021-06-20 --res=mail
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
    foreach ($refereeData as $refereeDatum) {
        $csvData[] = [
            $refereeDatum['rank'], $refereeDatum['referee_id'], $refereeDatum['mobile'], $refereeDatum['referee_cnt'], $refereeDatum['earliest_time'],
        ];
    }
    $path = '/tmp/statisticsJingDongKa.csv';
    if (isset($params['res']) && $params['res'] == 'mail') {
        sendMail($path, $csvData, '京东卡奖励结果排名');
    } else {
        showResult($path, $csvData);
    }
}

/**
 * 金叶子奖励结果
 * 测试脚本 php scripts/activity_duanwu.php statisticsJinYeZi --start=2021-06-10 --end=2021-06-20 --end1=2021-07-01 --refund=0 --res=mail
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
    
    $startDate = date('Ymd', $startTime);
    $endDate = date('Ymd', $endTime);
    
    $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    $maxId = 0;
    $refereeData = [];
    $statisticsTable = StudentReferralStudentStatisticsModel::getTableNameWithDb();
    $detailTable = StudentReferralStudentDetailModel::getTableNameWithDb();
    $studentTable = DssStudentModel::getTableNameWithDb();
    while (true) {
        $sql = "
            SELECT
                a.id,a.referee_id,a.student_id,b.mobile
            FROM
                {$statisticsTable} a
                INNER JOIN {$studentTable} b ON b.id = a.referee_id
            WHERE
                a.id > {$maxId}
                AND a.create_time >= {$startTime}
                AND a.create_time <= {$endTime}
                AND b.has_review_course = 2
                AND b.sub_start_date < {$endDate}
                AND b.sub_end_date > {$startDate}
                AND EXISTS ( SELECT 1 FROM {$detailTable} c WHERE c.student_id = a.student_id AND c.create_time >= {$startTime} AND c.create_time <= {$endTime1} AND c.stage = 2 )
            ORDER BY a.id ASC
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
    $path = '/tmp/statisticsJinYeZi.csv';
    if (isset($params['res']) && $params['res'] == 'mail') {
        sendMail($path, $csvData, '金叶子奖励结果排名');
    } else {
        showResult($path, $csvData);
    }
}

/**
 * 写入csv文件
 * @param $path
 * @param $data
 */
function saveCsvFile($path, $data)
{
    $fp = fopen($path, 'w+');
    foreach ($data as $value) {
        fputcsv($fp, $value);
    }
    fclose($fp);
}

/**
 * 直接cli输出结果
 * @param $path
 * @param $data
 */
function showResult($path, $data)
{
    saveCsvFile($path, $data);
    echo file_get_contents($path);
    unlink($path);
}

/**
 * 发送结果邮件
 * @param $path
 * @param $data
 * @param $content
 */
function sendMail($path, $data, $content)
{
    saveCsvFile($path, $data);
    PhpMail::sendEmail('sunchanghui@xiaoyezi.com', '端午节活动', $content, $path);
    unlink($path);
}
