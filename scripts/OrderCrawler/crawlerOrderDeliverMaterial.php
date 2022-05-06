<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/4/24
 * Time: 15:38
 */

namespace App\scripts\OrderCrawler;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/../..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CrawlerOrderModel;
use App\Services\Queue\DouStoreTopic;
use Dotenv\Dotenv;

/**
 * 脚本爬取抖店实物订单,推送erp，进行发货处理
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$notTime = time();
SimpleLogger::info('crawler order deliver material start', []);
//获取订单数据
$orderList = CrawlerOrderModel::getRecords([
    'dd_shop_id'      => CrawlerOrderModel::AI_DOU_DIAN_SHOP_ID,
    'receiver_tel[!]' => '',
    'is_send_erp'     => Constants::STATUS_FALSE,
    'LIMIT'           => 10,
], ['order_code', 'goods_code', 'dd_shop_id', 'receiver_name', 'receiver_tel', 'receiver_address']);

$erpRequestObj = new Erp();
$rdb = RedisDB::getConn();
foreach ($orderList as $ov) {
    $tmpLockCacheKey = CrawlerOrderModel::GOODS_CODE_PUSH_ERP_LOCK_CACHE_KEY . $ov['order_code'];
    $lockRes = $rdb->setnx($tmpLockCacheKey, Constants::STATUS_TRUE);
    if (empty($lockRes)) {
        continue;
    }
    $rdb->expire(CrawlerOrderModel::GOODS_CODE_PUSH_ERP_LOCK_CACHE_KEY, Util::TIMESTAMP_1H);
    $tmpMsgBody = [
        "guany_product_id" => $ov['goods_code'],
        "xyz_receiver_msg" => Util::authcode(json_encode([
            'name' => $ov['receiver_name'],
            'tel'  => $ov['receiver_tel'],
            'addr' => $ov['receiver_address'],
        ], JSON_UNESCAPED_UNICODE), '', CrawlerOrderModel::CRAWLER_ORDER_AUTH_KEY),
        "shop_id"          => $ov['dd_shop_id'],
        "update_time"      => time(),
        "s_ids"            => [$ov['order_code']],
        "p_id"             => $ov['order_code'],
    ];
    $requestResponse = $erpRequestObj->douStoreMsg([
        'topic_name'    => DouStoreTopic::TOPIC_NAME,
        'event_type'    => DouStoreTopic::EVENT_TYPE_DELIVER_MATERIAL_OBJECT,
        'source_app_id' => Constants::SELF_APP_ID,
        'exec_time'     => $notTime,
        'publish_time'  => $notTime,
        'msg_body'      => $tmpMsgBody
    ]);
    //请求失败
    if ($requestResponse === false) {
        $rdb->del([$tmpLockCacheKey]);
        continue;
    }
    $pushSuccessOrderIds[] = $ov['order_code'];
}
if (!empty($pushSuccessOrderIds)) {
    //修改数据
    CrawlerOrderModel::batchUpdateRecord(['is_send_erp' => Constants::STATUS_TRUE],
        ['order_code' => $pushSuccessOrderIds]);
}
SimpleLogger::info('crawler order deliver material end', []);