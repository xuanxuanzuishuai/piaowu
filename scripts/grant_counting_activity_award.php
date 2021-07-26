<?php
/**
 * 补偿机制
 * 计数任务奖励发放
 *
 * User: xingkuiYu
 * Date: 2021/7/15
 * Time: 15:58
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CountingActivityAwardModel;
use App\Services\CountingActivityAwardService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$startMemory    = memory_get_usage();
$execsStartTime = time();


SimpleLogger::info('grant counting activity award start', []);


$time = time() - Util::TIMESTAMP_1H;
//获取超过1小时未发放的数据
$award = CountingActivityAwardModel::getRecords([
    'shipping_status' => CountingActivityAwardModel::SHIPPING_STATUS_BEFORE,
    'create_time[<=]' => $time,
    'ORDER'           => ['create_time' => 'ASC'],
    'LIMIT'           => [0, 100]
]);
if (empty($award)) {
    return false;
}


foreach ($award as $item){

    switch ($item['type']) {
        case CountingActivityAwardModel::TYPE_GOLD_LEAF:
            CountingActivityAwardService::grantGoldLeaf($item);
            break;
        case CountingActivityAwardModel::TYPE_ENTITY:
            CountingActivityAwardService::grantEntity($item);
            break;
        default:
            SimpleLogger::error('counting_activity_award data type error', [$item]);
    }
}


$endMemory   = memory_get_usage();
$execEndTime = time();
SimpleLogger::info('send week reward massage end',
    ['memory' => ($endMemory - $startMemory), 'exec_time' => ($execEndTime - $execsStartTime)]);








