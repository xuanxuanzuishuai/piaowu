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
 * 每天凌晨1点，2点，3点，4点，各运行一次
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
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Dss\DssWechatOpenIdListModel;
use App\Services\Queue\QueueService;
use App\Services\WechatService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

const KEY_DAILY_WX_MENU_TAG = 'DAILY_WX_MENU_TAG_'; // 每天待更新数据
const KEY_DAILY_WX_MENU_TAG_MARK = 'DAILY_WX_MENU_TAG_MARK'; // 每天待更新数据是否完成组装

// QUERY：
// 1.查库中全量数据，能覆盖范围：年卡，体验
// 2.查询单独记录的7天内点击菜单记录，覆盖范围：注册用户
// 3.查询单独记录的点击菜单且不在库中的数据，覆盖范围：未绑定用户

$db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
$uw = DssUserWeiXinModel::$table;
$wol = DssWechatOpenIdListModel::$table;

$sql = "
SELECT 
    %s
FROM  $uw uw
INNER JOIN $wol wol ON wol.openid = uw.open_id
WHERE uw.app_id = :app_id
AND uw.user_type = :user_type
AND uw.busi_type = :busi_type
AND uw.status = :busi_type
AND wol.status = :sub_status
";
$map = [
    ':app_id'     => Constants::SMART_APP_ID,
    ':user_type'  => DssUserWeiXinModel::USER_TYPE_STUDENT,
    ':busi_type'  => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
    ':status'     => DssUserWeiXinModel::STATUS_NORMAL,
    ':sub_status' => DssWechatOpenIdListModel::SUBSCRIBE_WE_CHAT,
];
$field = ' count(uw.id) as total ';
$total = $db->queryAll(sprintf($sql, $field), $map);
$total = $total[0]['total'] ?? 0;
if (empty($total)) {
    SimpleLogger::error('NO DATA', [$map]);
    return;
}
$amount = 1000;

$field    = ' uw.id,uw.open_id ';
$hour     = date('H');
$date     = date('ymd');
$redis    = RedisDB::getConn();
$expire   = 172800;
$todayKey = KEY_DAILY_WX_MENU_TAG_MARK . $date;
$done     = $redis->get($todayKey);
$batchAmount = 8;
if (!$done) {
    // 创建分成4组的待更新openid数据
    for ($start = 0; $start <= $total; $start += $amount) {
        $batchSql = $sql . " ORDER BY uw.id LIMIT $start,$amount;";
        $records  = $db->queryAll(sprintf($batchSql, $field), $map);
        $records  = array_column($records, null, 'open_id');
        foreach ($records as $item) {
            $part = (fmod($item['id'], $batchAmount) + 1);
            $key = KEY_DAILY_WX_MENU_TAG . $part;
            $redis->lpush($key, [$item['open_id']]);
        }
    }

    // 未绑定用户
    $key = WechatService::KEY_WECHAT_USER_NOT_EXISTS . date('Ymd', strtotime('-1 day'));
    $item = '1';
    while (!empty($item)) {
        $item = $redis->rpop($key);
        if (empty($item)) {
            break;
        }
        $updateKey = KEY_DAILY_WX_MENU_TAG . mt_rand(1, $batchAmount);
        $redis->lpush($updateKey, [$item]);
    }
    $redis->del([$key]);

    for ($i = 0; $i < $batchAmount; $i ++) {
        $redis->expire(KEY_DAILY_WX_MENU_TAG . $i, $expire);
    }
    $redis->set($todayKey, time());
    $redis->expire($todayKey, $expire);
}

// 更新当前时间（小时）组的数据
$key = KEY_DAILY_WX_MENU_TAG . intval($hour);
$counter = 0;
$limit = 50;
$list = [];
while ($counter < $limit) {
    $item = $redis->rpop($key);
    if (empty($item)) {
        break;
    }
    $list[$item] = $item;
    $counter ++;
    if ($counter >= $limit) {
        QueueService::dailyUpdateUserMenuTag($list, 3600);
        $list = [];
        $counter = 0;
    }
}
if (!empty($list)) {
    QueueService::dailyUpdateUserMenuTag($list, 3600);
}
