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

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\ErpService\ErpGoodsV1Service;
use App\Services\Queue\GrantAwardTopic;
use Dotenv\Dotenv;

/**
 * 异步发送抽奖活动的实物奖品
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('async send lottery award start', []);
//转盘抽奖实物奖励,等待发货数据
$waitSendAwardRecordData = LotteryAwardRecordService::getUnshippedAwardRecord();
SimpleLogger::info("waitSendAwardRecordData", [$waitSendAwardRecordData]);
if (empty($waitSendAwardRecordData)) {
    return true;
}
foreach ($waitSendAwardRecordData as &$wv) {
    $wv += json_decode($wv['award_detail'], true);
}

//获取商品数据
$goodsIds = array_column($waitSendAwardRecordData, 'common_award_id');
$goodsData = array_column(ErpGoodsV1Service::getGoodsDataByIds($goodsIds), null, 'id');
//todo

SimpleLogger::info('async send lottery award end', []);
