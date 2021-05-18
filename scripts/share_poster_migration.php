<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * share_poster数据迁移
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

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssSharePosterModel;
use App\Models\SharePosterModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// QUERY：
// 1.查库中全量数据，能覆盖范围：年卡，体验
// 2.查询单独记录的7天内点击菜单记录，覆盖范围：注册用户
// 3.查询单独记录的点击菜单且不在库中的数据，覆盖范围：未绑定用户

$slaveDB = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
$source = DssSharePosterModel::getTableNameWithDb();

$sql = "
SELECT 
    %s
FROM $source sp
";
$map = [];
$field = ' count(sp.id) as total ';
$total = $slaveDB->queryAll(sprintf($sql, $field), $map);
$total = $total[0]['total'] ?? 0;
if (empty($total)) {
    SimpleLogger::error('NO DATA', [$map]);
    return;
}
$amount = 10;

$field    = ' sp.* ';
// 创建分成4组的待更新openid数据
$typeDict = [
    1 => 3,
];
for ($start = 0; $start <= $total; $start += $amount) {
    $insert = [];
    $batchSql = $sql . " ORDER BY sp.id LIMIT $start,$amount;";
    $records  = $slaveDB->queryAll(sprintf($batchSql, $field), $map);
    foreach ($records as $item) {
        if ($item['type'] == SharePosterModel::TYPE_RETURN_CASH) {
            continue;
        }
        $insert[] = [
            'student_id'      => $item['student_id'],
            'activity_id'     => $item['activity_id'],
            'award_id'        => $item['award_id'],
            'image_path'      => $item['img_url'],
            'type'            => $typeDict[$item['type']] ?? $item['type'],
            'verify_status'   => $item['status'],
            'verify_time'     => $item['check_time'],
            'verify_user'     => $item['operator_id'],
            'verify_reason'   => $item['reason'],
            'remark'          => $item['remark'],
            'points_award_id' => $item['points_award_id'],
            'create_time'     => $item['create_time'],
            'update_time'     => $item['update_time'],
        ];
    }
    if (!empty($insert)) {
        // print_r($insert);
        // die();
        $res = SharePosterModel::batchInsert($insert);
        if (empty($res)) {
            SimpleLogger::error('INSERT ERROR', $insert);
        }
    }
    echo $start."\t".$total.PHP_EOL;
}
