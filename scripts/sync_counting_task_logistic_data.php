<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/29
 * Time: 15:38
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Models\CountingActivityAwardModel;
use App\Services\Queue\GrantAwardTopic;
use Dotenv\Dotenv;

/**
 * 周周领奖任务之计数任务奖励，处理实物发货物流信息同步
 * 执行命令格式：sudo -u www-data php  /data/web/operation_backend/scripts/sync_counting_task_logistic_data.php
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('sync counting task logistic data start', []);
$startTime = time();
$startMemory = memory_get_usage();
//实物奖励数据列表:一个月之内，未签收，发货状态正常条件的实物奖励数据
$awardData = CountingActivityAwardModel::getRecords(
    [
        'type' => CountingActivityAwardModel::TYPE_ENTITY,
        'logistics_status[<]' => CountingActivityAwardModel::LOGISTICS_STATUS_SIGN,
        'shipping_status' => [
            CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
            CountingActivityAwardModel::SHIPPING_STATUS_DELIVERED,
            CountingActivityAwardModel::SHIPPING_STATUS_CENTRE,
        ],
        'create_time[>=]' => strtotime('-1 month'),
    ], ['id', 'unique_id']);
if (empty($awardData)) {
    SimpleLogger::info('award data empty', []);
    return true;
}

try {
    $topicObj = new GrantAwardTopic();
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
//聚合那边的接口频率是60次/s,建议每秒不超过20次
$totalCount = count($awardData);
$batchLimit = 20;
$delayTime = 0;
$batchTimes = ceil($totalCount / $batchLimit);
for ($i = 1; $i <= $batchTimes; $i++) {
    $tmpAwardData = array_slice($awardData, ($i - 1) * $batchLimit, $batchLimit);
    foreach ($tmpAwardData as $avl) {
        try {
            $topicObj->countingSyncAwardLogistics(['unique_id' => $avl['unique_id']])->publish($delayTime);
        } catch (\Exception $e) {
            SimpleLogger::error($e->getMessage(), ['data' => $avl]);
            return false;
        }
    }
    $delayTime += 2;
}
$endMemory = memory_get_usage();
$endTime = time();
SimpleLogger::info('sync counting task logistic data end', ['duration' => ($endTime - $startTime) . 's', 'memory' => (($endMemory - $startMemory) / 1024 / 1024) . 'M']);
