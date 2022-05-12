<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/04/19
 * Time: 15:38
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\Queue\GrantAwardTopic;
use Dotenv\Dotenv;

/**
 * 处理物流信息:此脚本负责当前op系统所有实物发货后，请求erp拉取物流信息
 * 当有新的活动发放实物时，参考已有逻辑，在此编写代码
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('sync logistic data start', []);
$startTime = time();
$startMemory = memory_get_usage();
//聚合那边的接口频率是60次/s,建议每秒不超过20次
$batchLimit = 20;
$delayTime = 0;
$totalAwardRecordList = [];
//转盘抽奖实物奖励数据
$lotteryAwardData = LotteryAwardRecordService::getUnreceivedAwardRecord();
if (!empty($lotteryAwardData)) {
    $totalAwardRecordList = array_map(function (&$v) {
        $v['event_type'] = GrantAwardTopic::LOTTERY_AWARD_LOGISTICS_SYNC;
        return $v;
    }, $lotteryAwardData);
}

//==========新的活动，物流信息代码从此开始编写=============

if (empty($totalAwardRecordList)) {
    SimpleLogger::info('award data empty', []);
    return true;
}

//获取发奖topic对象
try {
    $topicObj = new GrantAwardTopic();
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
$totalCount = count($totalAwardRecordList);
$batchTimes = ceil($totalCount / $batchLimit);
for ($i = 1; $i <= $batchTimes; $i++) {
    $tmpAwardData = array_slice($totalAwardRecordList, ($i - 1) * $batchLimit, $batchLimit);
    foreach ($tmpAwardData as $avl) {
        try {
            switch ($avl['event_type']) {
                case GrantAwardTopic::LOTTERY_AWARD_LOGISTICS_SYNC:
                    $topicObj->lotterySyncAwardLogistics(['unique_id' => $avl['unique_id']])->publish($delayTime);
                    break;
                default:
                    SimpleLogger::error("error event type", [$avl]);
            }
        } catch (\Exception $e) {
            SimpleLogger::error($e->getMessage(), ['data' => $avl]);
            return false;
        }
    }
    $delayTime += 5;
}
$endMemory = memory_get_usage();
$endTime = time();
SimpleLogger::info('sync logistic data end',
    ['duration' => ($endTime - $startTime) . 's', 'memory' => (($endMemory - $startMemory) / 1024 / 1024) . 'M']);
