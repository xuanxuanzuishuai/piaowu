<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * 每日判断用户tag，用于公众号个性化菜单
 * 2021年04月13日14:38:16
 * 每天凌晨非高峰运行
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
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\Queue\QueueService;
use App\Services\WechatService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// QUERY：
// 1.查库中全量数据，能覆盖范围：年卡，体验
// 2.查询单独记录的7天内点击菜单记录，覆盖范围：注册用户
// 3.查询单独记录的点击菜单且不在库中的数据，覆盖范围：未绑定用户

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
$amount = 1000;

for ($start = 0; $start <= $total; $start += $amount) {
    $where['LIMIT'] = [$start, $amount];
    $records = DssUserWeiXinModel::getRecords($where);
    $openIds = array_unique(array_column($records, 'open_id'));
    if (!empty($openIds)) {
        QueueService::dailyUpdateUserMenuTag($openIds);
    }
}

// 未绑定用户
$key = WechatService::KEY_WECHAT_USER_NOT_EXISTS . date('Ymd', strtotime('-1 day'));
$redis = RedisDB::getConn();
$counter = 0;
$openIds = [];
$limit = 100;
while ($counter < $limit) {
    $item = $redis->rpop($key);
    if (empty($item)) {
        break;
    }
    $counter++;
    $openIds[$item] = $item;
    if ($counter > $limit) {
        QueueService::dailyUpdateUserMenuTag($openIds);
        $counter = 0;
        $openIds = [];
    }
}
if (!empty($openIds)) {
    QueueService::dailyUpdateUserMenuTag($openIds);
}
