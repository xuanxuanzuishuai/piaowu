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

use App\Libs\SimpleLogger;
use App\Models\PlayClassRecordMessageModel;
use App\Services\PlayClassRecordService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

function logEcho($text, $data = null) {
    if (empty($text)) {
        return ;
    }
    echo $text . PHP_EOL;
    if (!empty($data)) {
        print_r($data);
    }

    SimpleLogger::info($text, $data ?? []);
}

logEcho("update play class records [START]");
logEcho("处理24小时内的上课模式数据更新消息");

$endTime = time();
$startTime = $endTime - 86400;

$startStr = date('Y-m-d H:i', $startTime);
$endStr = date('Y-m-d H:i', $endTime);
logEcho("$startStr - $endStr");

$count = PlayClassRecordMessageModel::getCount($startTime, $endTime);

$pageSize = 100;
$maxPage = ceil($count / $pageSize);

logEcho("count: $count");
logEcho("max page: $maxPage");

$success = 0;
for ($page = 0; $page < $maxPage; $page++) {
    $where = [
        'create_time[<>]' => [$startTime, $endTime],
        'status' => PlayClassRecordMessageModel::STATUS_INIT,
        "LIMIT" => $pageSize
    ];
    $rows = PlayClassRecordMessageModel::getRecords($where, '*', false);

    $pc = count($rows);
    logEcho(">> page: $page, count: $pc");

    foreach ($rows as $row) {
        $message = json_decode($row['body'], true);
        $result = PlayClassRecordService::handleClassUpdate($message['msg_body']);
        PlayClassRecordMessageModel::updateRecord($row['id'], [
            'status' => $result ? PlayClassRecordMessageModel::STATUS_SUCCESS : PlayClassRecordMessageModel::STATUS_FAILURE
        ]);
        if ($result) { $success++; }
    }
}

logEcho("success: $success");

logEcho("update play class records [END]");

logEcho("[update play class records] >>> ", [
    '$startTime' => $startTime,
    '$endTime' => $endTime,
    '$count' => $count,
    '$success' => $success
]);