<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/11
 * Time: 17:38
 */
/*
 * 体验班级5日打卡，需要推送的所有学生数据查询
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

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\RedisDB;
use App\Models\CHModel\AprViewStudentModel;
use App\Services\ActivityService;
use App\Services\ReferralService;
use DateTime;
use Dotenv\Dotenv;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Libs\Constants;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$now = time();

$redis = RedisDB::getConn();
$redisKey = "PUSH_CHECKIN_MESSAGE-".date('Ymd');
$wechat = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_WX_SERVICE);

// 查询所有目前在进行的体验课班级
$startTime = strtotime("-5 days", strtotime(date('Y-m-d', $now)));
$endTime   = strtotime(date('Y-m-d 24:00:00', $now));
$where = [];
$where['teaching_start_time[>=]'] = $startTime;
$where['teaching_start_time[<=]'] = $endTime;
$where['teaching_type'] = DssCollectionModel::TEACHING_TYPE_TRIAL;
$where['event_id']      = DictConstants::get(DictConstants::CHECKIN_PUSH_CONFIG, 'collection_event_id');

$allCollections   = DssCollectionModel::getRecords($where, ['id', 'teaching_start_time', 'teaching_end_time']);
$allCollectionIds = array_column($allCollections, 'id');
$collectionInfo   = array_combine($allCollectionIds, array_values($allCollections));

if (empty($allCollectionIds)) {
    SimpleLogger::info('EMPTY COLLECTION IDS', [$where]);
    return ;
}
// 所有学生：

$dayList = [];
$today = new DateTime(date('Y-m-d', $now));

// 只处理尾数为参数的班级数据
$arg = $argv[1] ?? 0;
foreach ($collectionInfo as $collectionId => $collection) {
    if (fmod($collectionId, 10) != $arg) {
        continue;
    }
    $allStudents = DssStudentModel::getByCollectionId($collectionId, true);

    foreach ($allStudents as $student) {
        $teachingStartTime = $collectionInfo[$student['collection_id']]['teaching_start_time'] ?? 0;
        $student['teaching_start_time'] = $teachingStartTime;
        if (empty($teachingStartTime)) {
            continue;
        }
        $startDay = new DateTime(date('Y-m-d', $teachingStartTime));
        $dayDiff = $today->diff($startDay)->format('%a');
        $student['day'] = $dayDiff;
        $dayList[$dayDiff] = $dayDiff;
        
        $day = date("Y-m-d", strtotime("+".($dayDiff-1)." days", $teachingStartTime));
        //$playInfo = DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord(intval($student['id']), strtotime($day), strtotime($day . ' 23:59:59'));
        $playInfo = AprViewStudentModel::getStudentBetweenTimePlayRecord(intval($student['id']), strtotime($day), strtotime($day . ' 23:59:59'));
        $sd = array_sum(array_column($playInfo, 'sum_duration'));
        $lc = count(array_unique(array_column($playInfo, 'lesson_id')));
        $student['wechat']        = ReferralService::getWechatInfoForPush($student);
        $student['lesson_count']  = $lc;
        $student['duration_sum']  = $sd ?? 0;
        if (!empty($student['duration_sum']) || empty($dayDiff)) {
            $params = [
                'from_type' => ActivityService::FROM_TYPE_PUSH
            ];
            list($content1, $content2, $posterImgFile) = ReferralService::getCheckinSendData($dayDiff, $student, $params);
            $student['content1']      = $content1;
            $student['content2']      = $content2;
            $student['posterImgFile'] = $posterImgFile;
            $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
        }
        $redis->hset($redisKey . '-' . $dayDiff, $student['id'], json_encode($student));
    }
}
foreach ($dayList as $day) {
    $redis->expire($redisKey . '-' . $day, 2*86400);
}
$redis->set($redisKey."done", '1');
$redis->expire($redisKey."done", 10*3600);
