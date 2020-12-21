<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/11
 * Time: 17:38
 */
/*
 * 体验班级5日打卡消息推送
 * 每日9点执行
 * 可接第一个参数：open_id, 调试模式，所有消息都发到参数传的open_id:
 * /usr/bin/php push_5days_checkin_message.php o9QIhwpOPSprL-zAvZlqSmWNA_EY
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
use App\Libs\RedisDB;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\MessageRecordLogModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$devFlag = false;
if (!empty($argv[1])) {
    $devFlag = $argv[1];
}

$wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
if (empty($wechat)) {
    SimpleLogger::error('wechat create fail', []);
    return ;
}
$redis = RedisDB::getConn();
$redisKey = "PUSH_CHECKIN_MESSAGE-".date('Ymd');

$done = $redis->get($redisKey.'done');
if (empty($done)) {
    SimpleLogger::error("NOT FINISHED", [$redisKey]);
    return;
}
for ($day = 0; $day < 6; $day++) {
    $tempKey = $redisKey . '-' . $day;
    $data = $redis->hgetall($tempKey);
    foreach ($data as $sutdentId => $student) {
        $student = json_decode($student, true);

        if ($devFlag !== false) {
            $student['open_id'] = $devFlag;
        }
        $student = checkStudent($day, $student);
        if ($student === false) {
            continue;
        }
        $content1 = $student['content1'] ?? '';
        $content2 = $student['content2'] ?? '';
        $posterImgFile = $student['posterImgFile'] ?? '';
        SimpleLogger::info("PUSH MESSAGE:", [$content1, $content2, $posterImgFile]);
        if (!empty($content1)) {
            $wechat->sendText($student['open_id'], $content1);
        }
        if (!empty($content2)) {
            $wechat->sendText($student['open_id'], $content2);
        }
        if (empty($posterImgFile['poster_save_full_path'])) {
            SimpleLogger::error('EMPTY POSTER URL', [$posterImgFile, $day, $student]);
            continue;
        }
        $tempMedia = $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
        //发送海报
        if (empty($tempMedia['media_id'])) {
            SimpleLogger::error('EMPTY MEDIA DATA', [$tempMedia, $posterImgFile]);
            continue;
        }
        $res = $wechat->sendImage($student['open_id'], $tempMedia['media_id']);

        $logRecord = [];
        $logRecord['open_id']       = $student['open_id'];
        $logRecord['activity_type'] = MessageRecordLogModel::ACTIVITY_TYPE_CHECKIN;
        $logRecord['relate_id']     = $day;
        $logRecord['push_res']      = MessageRecordLogModel::PUSH_FAIL;
        $logRecord['receive_res']   = MessageRecordLogModel::PUSH_FAIL;
        $logRecord['create_time']   = time();

        if (empty($res['errcode'])) {
            $logRecord['push_res']    = MessageRecordLogModel::PUSH_SUCCESS;
            $logRecord['receive_res'] = MessageRecordLogModel::PUSH_SUCCESS;
        }
        MessageRecordLogModel::insertRecord($logRecord);
    }
}

/**
 * @param $day
 * @param $student
 * @return false|array
 */
function checkStudent($day, $student)
{
    if (empty($student['open_id'])) {
        SimpleLogger::error('EMPTY OPEN ID', [$student]);
        return false;
    }
    if (empty($student['duration_sum']) && !empty($day)) {
        SimpleLogger::error('EMPTY DURATION', $student);
        return false;
    }
    return $student;
}
