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

use App\Libs\DictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\CrawlerOrderModel;
use App\Services\CrawlerOrder\DouDian\DdCrawlerDataService;
use App\Services\CrawlerOrder\GuanYi\CrawlerDataService;
use Dotenv\Dotenv;

/**
 * 脚本爬取抖店和管易店铺的订单信息
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('crawler dd and gy start', [time()]);
//读取配置
$dictConfig = DictConstants::get(DictConstants::CRAWLER_TARGET_SHOP_CONFIG, 'account_config_v1');
if (empty($dictConfig)) {
    die("商铺配置数据缺失/错误");
}
//拼接账户数据


$accountConfig = json_decode($dictConfig, true);
$rdb = RedisDB::getConn();
//账号可能是不同的第三方公司账号，而且还可以是多个
foreach ($accountConfig as &$dc) {
    $thirdServiceObj = null;
    $envConfig = explode('=>', $_ENV[$dc['env_name']]);
    $dc['account'] = $envConfig[0];
    $dc['pwd'] = $envConfig[1];
    //获取当前爬取账号可用状态
    if ($rdb->get(CrawlerOrderModel::ACCOUNT_CRAWLER_STATUS_CACHE_KEY . $dc['shop_id'] . '_' . $dc['account']) === "0") {
        continue;
    }
    switch ($dc['type']) {
        case CrawlerOrderModel::SOURCE_GY:
            $thirdServiceObj = new CrawlerDataService($dc);
            break;
        case CrawlerOrderModel::SOURCE_DD:
            $thirdServiceObj = new DdCrawlerDataService($dc);
            break;
        default:
            die("商铺账户类型错误");
    }
    if (!empty($thirdServiceObj->accessToken)) {
        break;
    }
}
if (empty($thirdServiceObj->accessToken)) {
    die("指定商铺登陆对象初始化失败！！！");
}
$thirdServiceObj->do();
SimpleLogger::info('crawler dd and gy end', [time()]);