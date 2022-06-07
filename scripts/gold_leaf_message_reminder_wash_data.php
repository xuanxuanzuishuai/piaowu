<?php

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Services\Queue\MessageReminder\MessageReminderTopic;
use Dotenv\Dotenv;

/**
 * event task金叶子提醒消息扫描脚本：初始化数据执行一次，但是此脚本支持多次执行，建议执行一次即可
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('gold leaf message reminder wash script start', []);
//查询即将过期数据
$db = MysqlDB::getDB(MysqlDB::CONFIG_ERP_SLAVE);
$data = $db->select(ErpUserEventTaskAwardGoldLeafModel::$table,
    [
        'id',
    ],
    [
        'status' => [
            ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING,
            ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED,
        ],
    ]);
if (empty($data)) {
    SimpleLogger::info('user event task award gold leaf data empty', []);
    return true;
}
//nsq对象
try {
    //每批次投递500条数据,5秒内消费完毕
    $batchLimit = 500;
    $nsqObj = new MessageReminderTopic();
    for ($i = 1; $i <= ceil(count($data) / $batchLimit); $i++) {
        $tmpData = array_slice($data, ($i - 1) * $batchLimit, $batchLimit);
        if (empty($tmpData)) {
            break;
        }
        $nsqObj->nsqDataSet(["award_ids" => array_column($tmpData, 'id')],
            $nsqObj::EVENT_TYPE_TASK_AWARD_MESSAGE_REMINDER)->publish(($i - 1) * 5);
    }
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
SimpleLogger::info('gold leaf message reminder wash script end', []);
