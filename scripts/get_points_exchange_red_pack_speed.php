<?php
/**
 * 查询积分兑换红包中状态是发放中的所有红包领取进度
 */

namespace App;

set_time_limit(0);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Models\UserPointsExchangeOrderWxModel;
use App\Services\CashGrantService;
use Dotenv\Dotenv;
use Elasticsearch\Common\Exceptions\RuntimeException;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

$where = [
    'status' => UserPointsExchangeOrderWxModel::STATUS_GIVE_ING,
];
$list = UserPointsExchangeOrderWxModel::getRecords($where);
foreach ($list as $item) {
    try {
        CashGrantService::updatePointsExchangeRedPackStatus($item['id']);
    }catch (RuntimeException $e) {
        SimpleLogger::info('script::get_points_exchange_red_pack_speed', ['info' => $e->getMessage(), 'award_info' => $item]);
    }
}