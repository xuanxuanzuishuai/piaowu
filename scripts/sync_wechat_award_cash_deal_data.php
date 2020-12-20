<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;
set_time_limit(0);
date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\SimpleLogger;
use App\Models\Dss\DssWechatAwardCashDealModel;
use App\Models\WeChatAwardCashDealModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
$baseId = 1;
$page = 100;
while (true) {
    $data = DssWechatAwardCashDealModel::getRecords(['id[>=]' => $baseId, 'id[<]' => $baseId + $page]);
    if (!empty($data)) {
        WeChatAwardCashDealModel::batchInsert($data);
        $baseId = $baseId + $page;
        SimpleLogger::info('now max id ', ['base_id' => $baseId]);
    } else {
        SimpleLogger::info('sync wechat award data exec end', []);
        break;
    }
}