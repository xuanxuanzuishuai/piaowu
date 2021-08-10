<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-07-28 19:10:22
 * Time: 10:10
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssMobileLogModel;
use App\Models\Dss\DssStudentModel;
use App\Models\LandingRecallLogModel;
use App\Models\LandingRecallModel;
use App\Services\LandingRecallService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$minTime = strtotime(date('YmdHi00'));

$db = MysqlDB::getDB();

$landingRecallTable = LandingRecallModel::$table;
$enableStatus = LandingRecallModel::ENABLE_STATUS_ON;
$sqlLandingRecall = "
    SELECT
        id,target_population,send_time,sms_content,voice_call_type,channel
    FROM {$landingRecallTable} WHERE enable_status = {$enableStatus};
";

$recalls = $db->queryAll($sqlLandingRecall);

$mobileLogTable = DssMobileLogModel::getTableNameWithDb();
$studentTable = DssStudentModel::getTableNameWithDb();

if ($recalls) {
    $redis = RedisDB::getConn();
    $redisKey = 'landing_recall_';
    $dbRO = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
    $url = DictConstants::get(DictConstants::LANDING_RECALL_URL, 'landing_recall_url');
    
    $channelTable = DssChannelModel::getTableNameWithDb();
    $channelStatus = DssChannelModel::STATUS_ENABLE;
    $sqlChannel = "SELECT id,parent_id FROM {$channelTable} WHERE `status` = '{$channelStatus}';";
    $channels = $dbRO->queryAll($sqlChannel);
    $channelMap = array_column($channels, 'parent_id', 'id');
    $channelLv1Map = [];
    foreach ($channelMap as $id => $parentId) {
        $tmpId = $id;
        $lv1ParentId = 0;
        $index = 0;
        while (true) {
            if (!isset($channelMap[$tmpId])) {
                break;
            }
            $parentId = $channelMap[$tmpId];
            if ($parentId == 0) {
                $lv1ParentId = $tmpId;
                break;
            }
            $tmpId = $parentId;
            if ($index++ >= 30) {   //防止错误数据发生死循环
                break;
            }
        }
        $channelLv1Map[$id] = $lv1ParentId;
    }
    
    foreach ($recalls as $recall) {
        $recallId = $recall['id'];
        $target = $recall['target_population'];
        $sendTime = $recall['send_time'];
        $smsContent = $recall['sms_content'];
        $voiceCallType = $recall['voice_call_type'];
        $channel = $recall['channel'];
        $endTime = $minTime - $sendTime * 60;
        $startTime = $endTime - 60;
        
        $validData = [];
        //未注册
        if ($target == LandingRecallModel::TARGET_UNREGISTER) {
            $sqlMobileLog = "
                SELECT
                    m.mobile,m.country_code
                FROM
                    {$mobileLogTable} m
                    LEFT JOIN {$studentTable} s ON m.mobile = s.mobile
                WHERE
                    m.create_time>={$startTime} AND m.create_time<{$endTime}
                    AND s.id IS NULL;
            ";
            $mobileInfos = $dbRO->queryAll($sqlMobileLog);
            foreach ($mobileInfos as $mobileInfo) {
                $mobile = $mobileInfo['mobile'];
                $countryCode = $mobileInfo['country_code'];
                $validData[] = [
                    'id' => $recallId,
                    'mobile' => $mobile,
                    'country_code' => $countryCode,
                ];
            }
        }
        
        //未付费
        if ($target == LandingRecallModel::TARGET_UNPAY) {
            $sqlMobileLog = "
                SELECT
                    m.mobile,m.country_code,s.channel_id
                FROM
                    {$mobileLogTable} m
                    INNER JOIN {$studentTable} s ON m.mobile = s.mobile
                WHERE
                    m.create_time>={$startTime} AND m.create_time<{$endTime}
                    AND s.has_review_course=0;
            ";
            $mobileInfos = $dbRO->queryAll($sqlMobileLog);
            $allowChannels = empty($channel)?[]:explode(',', $channel);
            foreach ($mobileInfos as $mobileInfo) {
                $channelId = $mobileInfo['channel_id'];
                $channelIdLevel1 = $channelLv1Map[$channelId] ?? 0;   //一级渠道ID
                //渠道合法
                if ($channelIdLevel1 && in_array($channelIdLevel1, $allowChannels)) {
                    $mobile = $mobileInfo['mobile'];
                    $countryCode = $mobileInfo['country_code'];
                    $validData[] = [
                        'id' => $recallId,
                        'mobile' => $mobile,
                        'country_code' => $countryCode,
                    ];
                }
            }
        }
        SimpleLogger::info('LANDING_RECALL_VALID_DATA_' . $minTime, ['recall' => $recall, 'data' => $validData]);
        //消息队列发送短信
        foreach ($validData as $validDatum) {
            $landingRecallId = $validDatum['id'];
            $mobile = $validDatum['mobile'];
            $countryCode = $validDatum['country_code'];
            
            $redisKeyF = $redisKey . $mobile . '_' . $sendTime;
            
            if (!$redis->exists($redisKeyF)) {
                LandingRecallService::sendSmsAndVoiceProduct($mobile, $countryCode, $url, $smsContent, $voiceCallType);
                $redis->setex($redisKeyF, Util::TIMESTAMP_ONEDAY, 1);
                $time = time();
                $date = date('Y-m-d', $time);
                $insertData = [
                    'landing_recall_id' => $landingRecallId,
                    'mobile' => $mobile,
                    'sms' => 1,
                    'voice' => $voiceCallType?1:0,
                    'create_date' => $date,
                    'create_time' => $time,
                ];
                
                LandingRecallLogModel::insertRecord($insertData);
            }
        }
    }
}

SimpleLogger::info("LANDING_RECALL_SEND_SMS_" . date('YmdHis'), []);
