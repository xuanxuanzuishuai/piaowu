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

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\RedisDB;
use App\Services\ReferralService;
use Dotenv\Dotenv;
use App\Models\Dss\DssCollectionModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssAIPlayRecordCHModel;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$redis = RedisDB::getConn();
$redisKey = "TRIAL_CLASS_PUSH_MESSAGE_CACHE-".date('Ymd');

// 查询所有目前在进行的体验课班级
$startTime = strtotime("-6 days", strtotime(date('Y-m-d', time())));
$endTime = strtotime(date('Y-m-d 24:00:00', time()));
$where = [];
$where['teaching_start_time[<=]'] = $endTime;
$where['teaching_end_time[>=]'] = $startTime;
$where['teaching_type'] = DssCollectionModel::TEACHING_TYPE_TRIAL;
// $where['event_id'] = '5'; // 指定打卡5日的EVENT ID

$allCollections = DssCollectionModel::getRecords($where, ['id', 'teaching_start_time', 'teaching_end_time']);
$allCollectionIds = array_column($allCollections, 'id');
$collectionInfo = array_combine($allCollectionIds, array_values($allCollections));
if (empty($allCollectionIds)) {
    SimpleLogger::info('EMPTY COLLECTION IDS', [$where]);
    return ;
}
// 所有学生：
$allStudents = DssStudentModel::getByCollectionId($allCollectionIds, true);

$dayInfoList = [];

$today = new \DateTime(date('Y-m-d', time()));
foreach ($allStudents as &$student) {
    $teachingStartTime = $collectionInfo[$student['collection_id']]['teaching_start_time'] ?? 0;
    $teachingEndTime = $collectionInfo[$student['collection_id']]['teaching_end_time'] ?? 0;
    $student['teaching_start_time'] = $teachingStartTime;
    if (empty($teachingStartTime) || empty($teachingEndTime)) {
        continue;
    }
    $startDay = new \DateTime(date('Y-m-d', $teachingStartTime));
    $dayInfoList[$today->diff($startDay)->format('%a')][$student['id']] = $student;
}
foreach ($dayInfoList as $dayDiff => &$value) {
    if (empty($dayDiff)) {
        continue;
    }
    foreach ($value as &$studentInfo) {
        $day = date("Y-m-d", strtotime("-".$dayDiff." days", $studentInfo['teaching_start_time']));
        $playInfo = DssAIPlayRecordCHModel::getStudentBetweenTimePlayRecord($studentInfo['id'], strtotime($day), strtotime($day . ' 23:59:59'));
        $studentInfo['lesson_count'] = $playInfo[0]['lesson_count'] ?? 0;
        $studentInfo['duration_sum'] = $playInfo[0]['duration_sum'] ?? 0;
        $studentInfo['score_final'] = $playInfo[0]['score_final'] ?? 0;
        $studentInfo['wechat'] = ReferralService::getWechatInfoForPush($studentInfo);
    }
}
if (!empty($dayInfoList)) {
    foreach ($dayInfoList as $day => $value) {
        $redis->hset($redisKey, $day, json_encode($value));
    }
    $redis->expire($redisKey, 2*86400);
}
