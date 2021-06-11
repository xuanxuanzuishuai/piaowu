<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * share_poster打卡审核原因修复
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
use App\Models\SharePosterModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$slaveDB = MysqlDB::getDB();
$source = SharePosterModel::$table;
$maxId = 0;

$sql = "
SELECT 
    %s
FROM $source sp
where `type` = :type
and `verify_status` = :verify_status
and `id` > :max_id
";
$map = [
    ':type' => SharePosterModel::TYPE_CHECKIN_UPLOAD,
    ':verify_status' => SharePosterModel::VERIFY_STATUS_UNQUALIFIED,
    ':max_id' => $maxId
];
$field = ' count(sp.id) as total ';
$total = $slaveDB->queryAll(sprintf($sql, $field), $map);
$total = $total[0]['total'] ?? 0;
if (empty($total)) {
    SimpleLogger::error('NO DATA', [$map]);
    return;
}
$amount = 1000;

$field    = ' sp.* ';

$reasonDict = [
    1 => 21,
    2 => 22,
    3 => 23
];
for ($start = 0; $start <= $total; $start += $amount) {
    $batchSql = $sql . " ORDER BY sp.id LIMIT 0,$amount;";
    $records  = $slaveDB->queryAll(sprintf($batchSql, $field), $map);
    foreach ($records as $item) {
        if ($item['type'] != SharePosterModel::TYPE_CHECKIN_UPLOAD) {
            continue;
        }
        if (empty($item['verify_reason'])) {
            continue;
        }
        $reasons = explode(',', $item['verify_reason']);
        $update = [];
        foreach ($reasons as $r) {
            $newR = $reasonDict[$r] ?? $r;
            $update[$newR] = $newR;
        }
        if (!empty($update)) {
            SharePosterModel::updateRecord($item['id'], ['verify_reason' => implode(',', $update)]);
        }
    }
    echo $start."\t".$total.PHP_EOL;
}
