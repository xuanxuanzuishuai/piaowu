<?php
/**
 * 每日重试积分兑换红包
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\UserPointsExchangeOrderModel;
use App\Models\UserPointsExchangeOrderWxModel;
use App\Services\Queue\UserPointsExchangeRedPackTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();


// 重试发放条件，只处理发放失败的
$where = [
    'status' => [
        // UserPointsExchangeOrderWxModel::STATUS_WAITING,
        UserPointsExchangeOrderWxModel::STATUS_GIVE_FAIL,
    ],
];
// 获取重试积分兑换红包的列表
$redPackList = UserPointsExchangeOrderWxModel::getRecords($where);
if (empty($redPackList)) {
    return "";
}
// 放入待发放红包队列
$topic = new UserPointsExchangeRedPackTopic();
foreach ($redPackList as $item) {
    $queueData = ['user_points_exchange_order_id' => $item['user_points_exchange_order_id'], 'record_sn' => $item['record_sn']];
    $topic->sendRedPack($queueData)->publish();
}