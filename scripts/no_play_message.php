<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * 未练琴消息发送
 * 每天10：00运行
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

use App\Libs\RedisDB;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$redis = RedisDB::getConn();
$redisKey = "PUSH_CHECKIN_MESSAGE-".date('Ymd');

for ($day = 1; $day < 5; $day++) {
    $tempKey = $redisKey . '-' . $day;
    $data = $redis->hgetall($tempKey);
    $openIds = [];
    foreach ($data as $studentId => $student) {
        $student = json_decode($student, true);
        if (!empty($student['duration_sum'])) {
            continue;
        }
        $openIds[$student['open_id']] =  ['name' => $student['name']];
    }
    if (!empty($openIds)) {
        QueueService::noPlayMessage($openIds, $day);
    }
}
