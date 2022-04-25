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
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CrawlerOrderModel;
use App\Services\Queue\DouStoreTopic;
use Dotenv\Dotenv;

/**
 * 脚本爬取抖店实物订单，进行发货处理
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('crawler order deliver material start', [time()]);
//获取订单数据
$orderList = CrawlerOrderModel::getRecords([
    'dd_shop_id'   => CrawlerOrderModel::AI_DOU_DIAN_SHOP_ID,
    'receiver_tel[!]' => '',
    'is_send_erp'     => Constants::STATUS_FALSE,
], ['order_code', 'goods_code', 'dd_shop_id', 'source', 'receiver_name', 'receiver_tel', 'receiver_address']);

$topic = new DouStoreTopic();

foreach ($orderList as $ov) {
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
    $topic->messageDelivery($tmpMsgBody, DouStoreTopic::EVENT_TYPE_DELIVER_MATERIAL_OBJECT)->publish(mt_rand(0, 300));
}
SimpleLogger::info('crawler order deliver material end', [time()]);