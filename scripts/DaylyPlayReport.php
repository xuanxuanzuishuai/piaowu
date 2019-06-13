<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/25
 * Time: 14:53
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\PlayRecordModel;
use App\Models\UserWeixinModel;
use App\Libs\UserCenter;
use App\Services\WeChatService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();
$db = MysqlDB::getDB();
$start_time = strtotime('today');
$end_time = $start_time + 86399;
$date = date("Y-m-d", $start_time);
$date_str = date("Y年m月d日", $start_time);

$sql = "select open_id, student_id from (select distinct student_id from " . PlayRecordModel::$table .
    " where created_time >= " . $start_time .
    " and " . PlayRecordModel::$table . ".created_time < " . $end_time . ") as stu_ids inner join " .
    UserWeixinModel::$table . " on stu_ids.student_id = " . UserWeixinModel::$table . ".user_id and " .
    UserWeixinModel::$table . ".app_id = " . UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . " and " .
    UserWeixinModel::$table . ".user_type = 1";

$userInfo = $db->queryAll($sql, []);
$data = [
    'first' => [
        'value' => "您的练琴日报已经生成，点击查看。",
        'color' => "#323d83"
    ],
    'keyword1' => [
        'value' => "练琴日报",
        'color' => "#323d83"
    ],
    'keyword2' => [
        'value' => $date_str,
        'color' => "#323d83"
    ],
    'remark' => [
        'value' => "点击【详情】查看",
        'color' => "#323d83"
    ]
];

//if (empty($userInfo)) {
//    $userInfo = [
//        [
//            "open_id" => "ordh90riaoetIHBnVC1s_UOMxbHk"
//        ]
//    ];
//}
$url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/daily?date=" . $date;
foreach ($userInfo as $value) {
    SimpleLogger::info("----", $value);
    // 发送学生练习日报
    $ret = WeChatService::notifyUserWeixinTemplateInfo(
        UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
        WeChatService::USER_TYPE_STUDENT,
        $value["open_id"],
        $_ENV["WECHAT_DAILY_RECORD_REPORT"],
        $data,
        $url
        );
    SimpleLogger::info("result:", $ret);
}
//foreach ($userInfo as $value) {
//    SimpleLogger::info("----", $value);
//    // 发送学生练习日报
//    $ret = WeChatService::notifyUserWeixinTextInfo(
//        UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
//        WeChatService::USER_TYPE_STUDENT,
//        $value["open_id"],
//        '您的练琴日报已经生成，点击<a href="' . $url . '"> 日报 </a>查看。'
//    );
//    SimpleLogger::info("result:", $ret);
//}
