<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/5/20
 * Time: 15:14
 */

/*
 * 月月有奖活动
 * 全量年卡用户
 * 执行时间
 *      周一 13点
 *      周三 20点
 *      周五 10点
 *      周日 19点
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

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$startMemory = memory_get_usage();
$execsStartTime = time();


SimpleLogger::info('send month reward massage start', []);

//判断是否执行
$week = date("w");
$hour = date("Hi");
$type = '';
$checkTime = 0;

switch ($week) {
    case 1:
        $type = PushMessageTopic::EVENT_MONTH_REWARD_MON;
        $checkTime = '1300';
        break;

    case 3:
        $type = PushMessageTopic::EVENT_MONTH_REWARD_WED;
        $checkTime = '2000';
        break;

    case 5:
        $type = PushMessageTopic::EVENT_MONTH_REWARD_FRI;
        $checkTime = '1000';
        break;

    case 0:
        $type = PushMessageTopic::EVENT_MONTH_REWARD_SUN;
        $checkTime = '1900';
        break;

    default:
        break;
}

if (!checkExecTime($hour, $checkTime)) {
    SimpleLogger::info('send month reward massage time error', [$week, $checkTime]);
    return false;
}

/**
 * 时间校验
 * @param int $current
 * @param int $check
 * @return bool
 */
function checkExecTime(int $current, int $check)
{
    if ($check - 10 < $current && $current < $check + 10) {
        return true;
    }
    return false;
}

//获取所有年卡用户
$student = DssStudentModel::getRecords([
    'has_review_course' => DssStudentModel::REVIEW_COURSE_1980,
    'sub_end_date[>]' => date("Ymd")
], ['id']);

$studentIds = array_chunk(array_column($student, 'id'), 1000);

foreach ($studentIds as $studentId) {
    $records = DssUserWeiXinModel::getRecords([
        'user_id' => $studentId,
        'status' => DssUserWeiXinModel::STATUS_NORMAL,
        'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
        'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
        'app_id' => Constants::SMART_APP_ID
    ], ['open_id']);

    $studentOpenIds = array_chunk($records, 50);

    foreach ($studentOpenIds as $openIdChunk) {
        $openIds = [];
        foreach ($openIdChunk as $value) {
            $openIds[$value['open_id']] = [];
        }
        if (empty($openIds)) {
            return false;
        }
        QueueService::weekAndMonthRewardMessage($openIds, $type);
    }
}

$endMemory = memory_get_usage();
$execEndTime = time();
SimpleLogger::info('send month reward massage end',
    ['memory' => ($endMemory - $startMemory), 'exec_time' => ($execEndTime - $execsStartTime)]);








