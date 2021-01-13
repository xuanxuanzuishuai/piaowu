<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * 每月活动消息推送
 * 2021年01月11日14:25:56
 * 每月1号10：00运行
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

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$where = [
    'app_id' => Constants::SMART_APP_ID,
    'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
    'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
    'status' => DssUserWeiXinModel::STATUS_NORMAL
];
$total = DssUserWeiXinModel::getCount($where);
if (empty($total)) {
    SimpleLogger::error('NO DATA', [$where]);
    return;
}
$amount = 10000;

for ($start = 0; $start <= $total; $start += $amount) {
    $where['LIMIT'] = [$start, $amount];
    $records = DssUserWeiXinModel::getRecords($where);
    $openIds = array_column($records, 'open_id');
    if (!empty($openIds)) {
        QueueService::monthlyEvent($openIds);
    }
}
