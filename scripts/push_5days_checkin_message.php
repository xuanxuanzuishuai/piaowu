<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/11
 * Time: 17:38
 */
/*
 * 体验班级5日打开，需要推送的所有学生数据查询
 * 每日9点前完成
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

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Libs\RedisDB;
use App\Libs\WeChat\WeChatMiniPro;
use App\Services\ReferralService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$redis = RedisDB::getConn();
$redisKey = "TRIAL_CLASS_PUSH_MESSAGE_CACHE-".date('Ymd');
$data = $redis->hgetall($redisKey);

$wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);
if (empty($wechat)) {
    SimpleLogger::error('wechat create fail', [$redisKey]);
    return ;
}
foreach ($data as $day => $value) {
    $value = json_decode($value, true);
    foreach ($value as $student) {
        $student = checkStudent($day, $student);
        if (!$student) {
            continue;
        }
        list($content1, $content2, $posterImgFile) = ReferralService::getCheckinSendData($day, $student);
        if (!empty($content1)) {
            $wechat->sendText($student['open_id'], $content1);
        }
        if (!empty($content2)) {
            $wechat->sendText($student['open_id'], $content2);
        }
        if (empty($posterImgFile['poster_save_full_path'])) {
            SimpleLogger::error('EMPTY POSTER URL', [$posterImgFile, $day, $student]);
        }
        $data = $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
        //发送海报
        if (empty($data['media_id'])) {
            SimpleLogger::error('EMPTY MEDIA DATA', [$data, $posterImgFile]);
            continue;
        }
        $wechat->sendImage($student['open_id'], $data['media_id']);
    }
}

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
