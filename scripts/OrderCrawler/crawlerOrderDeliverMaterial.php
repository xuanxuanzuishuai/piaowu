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
use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CrawlerOrderModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Services\Queue\DouStoreTopic;
use Dotenv\Dotenv;

/**
 * 脚本爬取抖店实物订单,推送erp，进行发货处理
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('crawler order deliver material start', []);
//获取有效的goods code
$goodsCodeDictConfig = array_column(DictConstants::getErpDictArr(DictConstants::DOU_CODE_TO_PACKAGE['type'])[DictConstants::DOU_CODE_TO_PACKAGE['type']],
	'code');
SimpleLogger::info("valid goods code", [$goodsCodeDictConfig]);
if (empty($goodsCodeDictConfig)) {
	return true;
}
//获取订单数据:每次处理100条
$orderList = CrawlerOrderModel::getRecords([
	'dd_shop_id'      => CrawlerOrderModel::AI_DOU_DIAN_SHOP_ID,
	'receiver_tel[!]' => '',
	'is_send_erp'     => Constants::STATUS_FALSE,
	'goods_code'      => $goodsCodeDictConfig,
	'LIMIT'           => 100,
], ['order_code', 'goods_code', 'dd_shop_id', 'receiver_name', 'receiver_tel', 'receiver_address']);

//获取goods code对应的课包ID
$goodsCodeDictConfig = array_column(DictConstants::getErpDictArr(DictConstants::DOU_CODE_TO_PACKAGE['type'])[DictConstants::DOU_CODE_TO_PACKAGE['type']], 'value', 'code');
//获取课包数据
$packageData = array_column(ErpPackageV1Model::getRecords(['id' => array_unique($goodsCodeDictConfig)], ['id', 'app_id', 'sale_shop', 'type']), null, 'id');
$nsqObj = new DouStoreTopic();
$orderLockKeys = [];
$rdb = RedisDB::getConn();
foreach ($orderList as $ov) {
	$tmpLockCacheKey = CrawlerOrderModel::GOODS_CODE_PUSH_ERP_LOCK_CACHE_KEY . $ov['order_code'];
	$lockRes = $rdb->setnx($tmpLockCacheKey, Constants::STATUS_TRUE);
	if (empty($lockRes)) {
		continue;
	}
	$rdb->expire($tmpLockCacheKey, Util::TIMESTAMP_1H);
	$orderLockKeys[] = $tmpLockCacheKey;
	//抖店商品对应的小叶子系统的产品课包数据
	$tmpGoodsCodeMapPackageData = $packageData[$goodsCodeDictConfig[$ov['goods_code']]];
	//字段说明文档:https://dowbo10hxj.feishu.cn/docx/doxcn5AhW7F1AqWSyzkJD3rWagK
	$tmpMsgBody = [
		"third_party_shop" => "doudian",//第三方店铺类型
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
		"xyz_package"      => [
			"id"        => (int)$tmpGoodsCodeMapPackageData['id'],
			"app_id"    => (int)$tmpGoodsCodeMapPackageData['app_id'],
			"sale_shop" => (int)$tmpGoodsCodeMapPackageData['sale_shop'],
			"type"      => (int)$tmpGoodsCodeMapPackageData['type'],
		]
	];
	//投递队列
	$nsqObj->nsqDataSet($tmpMsgBody, DouStoreTopic::EVENT_TYPE_THIRDPARTYORDER_PAID)->publish();
	$pushSuccessOrderIds[] = $ov['order_code'];
}
if (!empty($pushSuccessOrderIds)) {
	//修改数据
	CrawlerOrderModel::batchUpdateRecord(['is_send_erp' => Constants::STATUS_TRUE],
		['order_code' => $pushSuccessOrderIds]);
}
if (!empty($orderLockKeys)) {
	$rdb->del($orderLockKeys);
}
SimpleLogger::info('crawler order deliver material end', []);