<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/22
 * Time: 11：20
 */

/*
 * 微信自定义菜单
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\LotteryActivityModel;
use App\Models\LotteryAwardInfoModel;
use App\Models\LotteryAwardRecordModel;
use App\Services\Activity\Lottery\LotteryServices\LotteryActivityService;
use App\Services\Activity\Lottery\LotteryServices\LotteryImportUserService;
use Dotenv\Dotenv;
use Medoo\Medoo;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

//获取符合时间的抽奖活动
$time = time();
$activityList = LotteryActivityModel::getRecords([
    'start_time[<]' => $time,
    'end_time[>]'   => $time,
    'status'        => 2,
], ['op_activity_id', 'app_id', 'start_pay_time', 'end_pay_time']);

if (empty($activityList)) {
    SimpleLogger::info('lottery activity is empty', []);
    return false;
}

foreach ($activityList as $value) {
    //检查奖品库存
    checkStorage($value['op_activity_id']);
    //检查个人抽奖次数和已抽奖次数
    checkLotteryTimes($value['op_activity_id'], $value['app_id'], $value['start_pay_time'], $value['end_pay_time']);
    //检查抽奖比例
    checkLotteryRate($value['op_activity_id'], $time);
}
return false;


function checkStorage($opActivityId)
{
    $awardInfo = LotteryAwardInfoModel::getRecords([
        'op_activity_id' => $opActivityId,
        'num[>]'         => 0,
    ], ['id', 'consume' => Medoo::raw('num-rest_num'),]);
    if (empty($awardInfo)) {
        return false;
    }
    $awardRecord = LotteryAwardRecordModel::getRecords([
        'op_activity_id' => $opActivityId,
        'GROUP'          => 'award_id',
    ], ['num' => Medoo::raw('count(1)'), 'award_id']);
    if (empty($awardRecord)) {
        return false;
    }
    $awardInfoById = array_column($awardInfo, 'consume', 'id');
    $awardRecordById = array_column($awardRecord, 'num', 'award_id');

    foreach ($awardInfoById as $k => $v) {
        if (empty($awardRecordById[$k])) {
            continue;
        }
        if ($v != $awardRecordById[$k]) {
            $message = '抽奖活动ID：' . $opActivityId . PHP_EOL . '奖品ID：' . $k . PHP_EOL . '库存消耗和抽中奖品数量不匹配，请及时检查！';
            Util::sendFsWaringText($message, $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            break;
        }
    }
    return true;
}

function checkLotteryTimes($opActivityId, $appId, $startPayTime, $endPayTime)
{
    $awardRecord = LotteryAwardRecordModel::getRecords([
        'op_activity_id' => $opActivityId,
        'GROUP'          => 'uuid',
        'ORDER'          => ["num" => "DESC"],
        'LIMIT'          => 10,
    ], ['num' => Medoo::raw('count(1)'), 'uuid']);
    if (empty($awardRecord)) {
        return false;
    }

    foreach ($awardRecord as $value) {
        $totalTimes = getTotalTimes($opActivityId, $appId, $value['uuid'], $startPayTime, $endPayTime);
        if ($totalTimes < $value['num']) {
            $message = '抽奖活动ID：' . $opActivityId . PHP_EOL . '用户UUID：' . $value['uuid'] . PHP_EOL . '用户抽奖次数异常，请及时检查！';
            Util::sendFsWaringText($message, $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            break;
        }
    }
    return true;
}

function getTotalTimes($opActivityId, $appId, $uuid, $startPayTime, $endPayTime)
{
    //获取订单抽奖机会
    $orderInfo = LotteryActivityService::getOrderInfo($appId, $uuid, $startPayTime, $endPayTime);
    $orderToTimes = LotteryActivityService::orderToTimes($opActivityId, $orderInfo);
    $filerTimes = count($orderToTimes);
    //获取导入抽奖机会
    $importTimes = LotteryImportUserService::importUserTimes($opActivityId, $uuid);
    return $filerTimes + $importTimes;
}


function checkLotteryRate($opActivityId, $time)
{
    $awardInfo = removeAwardInfo($opActivityId, $time);
    if (empty($awardInfo)) {
        return false;
    }
    $awardId = array_column($awardInfo, 'id');
    $awardRecord = LotteryAwardRecordModel::getRecords([
        'op_activity_id' => $opActivityId,
        'use_type'       => LotteryAwardRecordModel::USE_TYPE_IMPORT,
        'award_id'       => $awardId,
        'GROUP'          => 'award_id',
    ], ['num' => Medoo::raw('count(1)'), 'award_id']);
    if (empty($awardRecord) || array_sum(array_column($awardRecord, 'num')) < 100) {
        return false;
    }

    $awardInfoRate = getRate($awardInfo, 'weight', 'id');
    $awardRecordRate = getRate($awardRecord, 'num', 'award_id');
    foreach ($awardInfoRate as $k => $v) {
        if (empty($awardRecordRate[$k])) {
            continue;
        }
        if (abs($v - $awardRecordRate[$k]) > 10) {
            $message = '抽奖活动ID：' . $opActivityId . PHP_EOL . '奖品ID：' . $k . PHP_EOL . '导入机会中奖比例异常，请及时检查！';
            Util::sendFsWaringText($message, $_ENV["FEISHU_DEVELOPMENT_TECHNOLOGY_ALERT_ROBOT"]);
            break;
        }
    }
}

function removeAwardInfo($opActivityId, $time)
{
    $awardInfo = LotteryAwardInfoModel::getRecords([
        'op_activity_id' => $opActivityId,
        'rest_num[>]'    => 0,
    ], ['id', 'weight', 'hit_times']);
    if (empty($awardInfo)) {
        return [];
    }
    foreach ($awardInfo as $key => $value) {
        $inTime = false;
        if (!empty($value['hit_times'])) {
            $hitTimes = json_decode($value['hit_times'], true);
            foreach ($hitTimes as $ht) {
                if (($time >= $ht['start_time']) && ($time <= $ht['end_time'])) {
                    $inTime = true;
                }
            }
        }
        if ($inTime == false) {
            unset($awardInfo[$key]);
            continue;
        }
    }
    return $awardInfo ?: [];
}

function getRate($arr, $weightName, $index)
{
    $total = array_sum(array_column($arr, $weightName));
    foreach ($arr as $value) {
        $res[$value[$index]] = round($value[$weightName] / $total, 2) * 100;
    }
    return $res ?? [];
}