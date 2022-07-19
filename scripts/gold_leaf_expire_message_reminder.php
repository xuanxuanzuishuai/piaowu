<?php

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Erp\ErpStudentAccountModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\StudentMessageReminderModel;
use App\Services\Queue\MessageReminder\MessageReminderTopic;
use Dotenv\Dotenv;

/**
 * 即将过期金叶子提醒消息扫描脚本:每天凌晨零点执行
 */
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
SimpleLogger::info('expire gold leaf message reminder start', []);
//查询即将过期数据
$startTimeData = Util::getStartEndTimestamp(time());
$expireTime = strtotime("+60 day", $startTimeData[1]);
$db = MysqlDB::getDB(MysqlDB::CONFIG_ERP_SLAVE);
$data = $db->select(ErpStudentAccountModel::$table,
    [
        '[>]' . ErpStudentModel::$table => ['student_id' => 'id'],
    ],
    [
        ErpStudentAccountModel::$table . '.num',
        ErpStudentAccountModel::$table . '.expire_time',
        ErpStudentAccountModel::$table . '.id',
        ErpStudentModel::$table . '.uuid',
    ],
    [
        ErpStudentAccountModel::$table . '.expire_time' => [
            $expireTime,
            $expireTime + 1,//后台充值的是到0点,这里兼容一下
        ],
        ErpStudentAccountModel::$table . '.app_id'      => Constants::SMART_APP_ID,
        ErpStudentAccountModel::$table . '.sub_type'    => ErpStudentAccountModel::SUB_TYPE_GOLD_LEAF,
        ErpStudentAccountModel::$table . '.num[>]'      => 0,
    ]);
if (empty($data)) {
    SimpleLogger::info('expire gold leaf data empty', []);
    return true;
}
//nsq对象
try {
    $nsqObj = new MessageReminderTopic();
    foreach ($data as $dv) {
        $tmpFormatParams = [
            'title'        => $dv['num'] / 100,
            'content'      => "积分将于" . date('Y年m月d日', $dv['expire_time']) . '过期',
            'data_id'      => $dv['id'],
            'student_uuid' => $dv['uuid'],
            'href_url'     => '',
            'message_type'     => StudentMessageReminderModel::MESSAGE_TYPE_GOLD_LEAF_60_EXPIRATION_REMINDER,
        ];
        $nsqObj->nsqDataSet($tmpFormatParams, $nsqObj::EVENT_TYPE_MESSAGE_REMINDER)->publish(mt_rand(0, 10));
    }
} catch (\Exception $e) {
    SimpleLogger::error($e->getMessage(), []);
    return false;
}
SimpleLogger::info('expire gold leaf message reminder end', []);
