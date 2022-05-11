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
use App\Models\Erp\ErpStudentModel;
use App\Models\LotteryActivityModel;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\ErpService\ErpGoodsV1Service;
use App\Services\Queue\GrantAwardTopic;
use Dotenv\Dotenv;

/**
 * 异步发送抽奖活动的实物奖品:领取奖品24小时后发货，即24小时后推送到ERP，晚11点~早8点不推送
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
//获取学生数据
$uuids = array_column($waitSendAwardRecordData, 'uuid');
$studentData = array_column(ErpStudentModel::getRecords(['uuid' => array_unique($uuids)], ['mobile', 'uuid']), null,
    'uuid');

//获取发奖topic对象
try {
    $topicObj = new GrantAwardTopic();
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
foreach ($waitSendAwardRecordData as $wak => $wav) {
    $nsqData = [
        'record_id'  => $wav['id'],
        'type'       => $wav['award_type'],
        'unique_id'  => $wav['unique_id'],
        'plat_id'    => Constants::UNIQUE_ID_PREFIX,
        'app_id'     => $wav['app_id'],
        'sale_shop'  => LotteryActivityModel::BUSINESS_MAP_SHOP[$wav['app_id']],
        'goods_id'   => $wav['common_award_id'],
        'goods_code' => $goodsData[$wav['common_award_id']]['code'],
        'mobile'     => $studentData[$wav['uuid']]['mobile'],
        'uuid'       => $wav['uuid'],
        'amount'     => $wav['common_award_amount'],
        'erp_address_id' => $wav['erp_address_id'],
    ];
    $topicObj->lotteryGrantAward($nsqData)->publish($wak % 600);
}
SimpleLogger::info('async send lottery award end', []);
