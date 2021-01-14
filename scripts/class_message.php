<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/11
 * Time: 14:25
 */

/**
 * 班级消息发送
 * 2021年01月11日14:25:56
 * 开班前1天
 * 开班前2天
 * 结班后1天
 * 开班当天
 * 开班7天
 * 每天20：00运行
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

use App\Libs\DictConstants;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\QueueService;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssStudentModel;
use App\Libs\Util;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$today = strtotime(date('Y-m-d'));
$teachStartTimeArr = [
    [
        // 开班当天
        'start_time' => $today,
        'type' => PushMessageTopic::EVENT_START_CLASS
    ],
    [
        // 开班前1天
        'start_time' => $today + (Util::TIMESTAMP_ONEDAY * 1),
        'type' => PushMessageTopic::EVENT_BEFORE_CLASS_ONE
    ],
    [
        // 开班前2天
        'start_time' => $today + (Util::TIMESTAMP_ONEDAY * 2),
        'type' => PushMessageTopic::EVENT_BEFORE_CLASS_TWO
    ],
    [
        // 结班后1天
        'start_time' => $today - (Util::TIMESTAMP_ONEDAY * 6),
        'type' => PushMessageTopic::EVENT_AFTER_CLASS_ONE
    ],
    [
        // 开班后7天
        'start_time' => $today - (Util::TIMESTAMP_ONEDAY * 7),
        'type' => PushMessageTopic::EVENT_START_CLASS_SEVEN
    ],
];

foreach ($teachStartTimeArr as $item) {
    $where = [];
    $where['teaching_start_time[>=]'] = $item['start_time'];
    $where['teaching_start_time[<]'] = $item['start_time'] + Util::TIMESTAMP_ONEDAY;
    $where['teaching_type'] = DssCollectionModel::TEACHING_TYPE_TRIAL;

    $allCollections = DssCollectionModel::getRecords($where, ['id', 'teaching_start_time', 'teaching_end_time']);
    $collectionInfo = array_column($allCollections, null, 'id');
    foreach ($collectionInfo as $collectionId => $collection) {
        $allStudents = DssStudentModel::getByCollectionId($collectionId, true);
        $data = array_column($allStudents, null, 'open_id');
        if (!empty($data)) {
            QueueService::classMessage($data, $item['type']);
        }
    }
}
