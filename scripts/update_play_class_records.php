<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/6
 * Time: 11:07 AM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\PlayClassRecordMessageModel;
use App\Services\PlayClassRecordService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

echo "update play class records [START]\n";
echo "处理24小时内的上课模式数据更新消息\n";

$endTime = time();
$startTime = $endTime - 86400;

echo date('Y-m-d H:i', $startTime) . ' - ' . date('Y-m-d H:i', $endTime) . "\n";

$count = PlayClassRecordMessageModel::getCount($startTime, $endTime);

$pageSize = 100;
$maxPage = ceil($count / $pageSize);

echo "count: $count\n";
echo "max page: $maxPage\n";

for ($page = 0; $page < $maxPage; $page++) {
    $where = [
        'create_time[<>]' => [$startTime, $endTime],
        "LIMIT" => [$page * $pageSize, $pageSize],
    ];
    $rows = PlayClassRecordMessageModel::getRecords($where, '*', false);

    foreach ($rows as $row) {
        $message = json_decode($row['body']);
        $result = PlayClassRecordService::handleClassUpdate($message['msg_body']);
        if ($result) {
            PlayClassRecordMessageModel::delete($row['id']);
        }
    }
}

echo "update play class records [END]\n";