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
use App\Libs\Util;
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

$sql = "SELECT
    records.student_id,
    uw.open_id,
    COUNT(DISTINCT lesson_id) AS lesson_count,
    SUM(duration) AS sum_duration
FROM
    ((SELECT
        student_id, lesson_id, duration
    FROM
        play_record
    WHERE
        created_time >= :start_time
            AND created_time < :end_time) UNION ALL (SELECT
        student_id, lesson_id, duration
    FROM
        play_class_record
    WHERE
        create_time >= :start_time
            AND create_time < :end_time)) records
        INNER JOIN
    user_weixin AS uw ON records.student_id = uw.user_id
        AND uw.app_id = 8
        AND uw.user_type = 1
GROUP BY records.student_id
;";
$map = [
    ':start_time' => $start_time,
    ':end_time' => $end_time,
];

$userInfo = $db->queryAll($sql, $map);

$url = $_ENV["WECHAT_FRONT_DOMAIN"] . "/student/daily?date=" . $date;
foreach ($userInfo as $value) {
    SimpleLogger::info("----", $value);
    $data = [
        'first' => [
            'value' => "宝贝今天的练琴日报已生成，宝贝很棒哦！继续加油！",
            'color' => "#323d83"
        ],
        'keyword1' => [
            'value' => $date,
            'color' => "#323d83"
        ],
        'keyword2' => [
            'value' => $value['lesson_count'] . "首",
            'color' => "#323d83"
        ],
        'keyword3' => [
            'value' => "练琴" . Util::formatExerciseTime($value['sum_duration']),
            'color' => "#323d83"
        ],
        'remark' => [
            'value' => "点击【详情】查看",
            'color' => "#323d83"
        ]
    ];
    // 发送学生练习日报
    $ret = WeChatService::notifyUserWeixinTemplateInfo(
        UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
        WeChatService::USER_TYPE_STUDENT,
        $value["open_id"],
        $_ENV["WECHAT_DAY_PLAY_REPORT"],
        $data,
        $url
    );
    SimpleLogger::info("result:", $ret);
}