<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 3:19 PM
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\CollectionModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Services\MessageService;
use Dotenv\Dotenv;


$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
//开班当天和开班7天的消息推送
list($nowRuleId, $sevenRuleId) = DictConstants::getValues(DictConstants::MESSAGE_RULE, ['start_class_day_rule_id', 'start_class_seven_day_rule_id']);
$teachStartTimeArr = [
   ['start_time' => strtotime(date('Y-m-d')), 'rule_id' => $nowRuleId],
   ['start_time' => strtotime(date('Y-m-d')) - Util::TIMESTAMP_ONEWEEK, 'rule_id' => $sevenRuleId]
];
array_map(function ($item) use($teachStartTimeArr){
    $studentInfo = MysqlDB::getDB()->select(
        CollectionModel::$table,
        [
            '[><]' . StudentModel::$table => ['id' => 'collection_id'],
            '[><]' . UserWeixinModel::$table => [StudentModel::$table . '.id' => 'user_id']
        ],
        [
            UserWeixinModel::$table . '.open_id'
        ],
        [
            CollectionModel::$table . '.teaching_start_time' => $item['start_time'],
            CollectionModel::$table . '.teaching_type' => CollectionModel::COLLECTION_TEACHING_TYPE_EXPERIENCE_CLASS,
            UserWeixinModel::$table. '.user_type' => UserWeixinModel::USER_TYPE_STUDENT,
            UserWeixinModel::$table.'.status' => UserWeixinModel::STATUS_NORMAL,
            UserWeixinModel::$table . '.busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
            UserWeixinModel::$table . '.app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT
        ]
    );
    MessageService::sendMessage(array_column($studentInfo, 'open_id'), $item['rule_id']);
}, $teachStartTimeArr);



