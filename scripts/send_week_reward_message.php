<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/5/19
 * Time: 19:17
 */

/*
 * 周周有礼活动
 * 执行时间
 *      周一 19点30分
 *      周二 19点30分
 *      周三 18点
 *      周四 19点30分
 *      周五 19点30分
 *      周六 16点30分
 *      周日 20点
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
use App\Models\OperationActivityModel;
use App\Models\SharePosterModel;
use App\Models\WeekActivityModel;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\QueueService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$startMemory = memory_get_usage();
$execsStartTime = time();


SimpleLogger::info('send week reward massage start', []);

//判断是否执行
$week = date("w");
$hour = date("Hi");
$type = '';
$checkTime = 0;
switch ($week) {
    case 1:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_MON;
        $checkTime = '1930';
        break;

    case 2:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_TUE;
        $checkTime = '1930';
        break;

    case 3:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_WED;
        $checkTime = '1800';
        break;

    case 4:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_THUR;
        $checkTime = '1930';
        break;

    case 5:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_FRI;
        $checkTime = '1930';
        break;

    case 6:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_SAT;
        $checkTime = '1630';
        break;

    case 0:
        $type = PushMessageTopic::EVENT_WEEK_REWARD_SUN;
        $checkTime = '2000';
        break;

    default:
        break;
}

if (!checkExecTime($hour, $checkTime)) {
    SimpleLogger::info('send week reward massage time error', [$week, $checkTime]);
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

$time = time();
//获取周周有礼活动信息
$activityInfo = WeekActivityModel::getRecord([
    'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
    'start_time[<=]' => $time,
    'end_time[>=]' => $time,
    'ORDER' => ['create_time' => 'DESC'],
], ['activity_id']);
if (empty($activityInfo)) {
    SimpleLogger::info('send week reward massage activity not find', [$week, $checkTime]);
    return false;
}

//查询已参加活动的用户信息
$sharePoster = SharePosterModel::getRecords(
    [
        'activity_id' => $activityInfo['activity_id'],
        'type' => SharePosterModel::TYPE_WEEK_UPLOAD
    ], ['student_id']);

//获取所有年卡用户
$student = DssStudentModel::getRecords([
    'has_review_course' => DssStudentModel::REVIEW_COURSE_1980,
    'sub_end_date[>]' => date("Ymd")
], ['id']);

$studentIds = array_column($student, 'id');
$joinActivityStudentIds = array_column($sharePoster, 'student_id');

$diff = array_diff($studentIds, $joinActivityStudentIds);

$diffStudentIds = array_chunk($diff, 1000);

foreach ($diffStudentIds as $diffStudentId) {
    $records = DssUserWeiXinModel::getRecords([
        'user_id' => $diffStudentId,
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
SimpleLogger::info('send week reward massage end',
    ['memory' => ($endMemory - $startMemory), 'exec_time' => ($execEndTime - $execsStartTime)]);








