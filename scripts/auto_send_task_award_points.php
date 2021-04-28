<?php
/**
 * 定时发送到期的积分
 */
namespace App;

set_time_limit(0);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpEventModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\CashGrantService;
use App\Services\Queue\PushMessageTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT,'.env');
$dotenv->load();
$dotenv->overload();

// 获取到期发待发放和发放失败的积分列表
$where = [
    'status' => [ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING, ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_FAIL],
];
$pointsList= ErpUserEventTaskAwardGoldLeafModel::getRecords($where);
if (empty($pointsList)) {
    SimpleLogger::info("script::auto_send_task_award_points",['info' => "is_empty_points_list", 'where' => $where]);
    return 'success';
}

// 获取完成任务的人uuid 和 id
$studentUuid = array_column($pointsList, 'finish_task_uuid');
$studentIdList = DssStudentModel::getRecords(['uuid' => $studentUuid], ['id','uuid']);
$studentIdToUuid = array_column($studentIdList,'uuid', 'id');
// 获取转介绍关系
$refList = StudentReferralStudentStatisticsModel::getRecords(['student_id' => array_column($studentIdList, 'id')]);
// 取得介绍人的uuid 和 id
$refUuidList = DssStudentModel::getRecords(['id' => array_column($refList, 'referee_id')], ['id', 'uuid']);
$refUuidArr = array_column($refUuidList, 'uuid', 'id');
// 整理用户推荐的uuid
$studentRef=[];
foreach ($refList as $item) {
    // 被推荐人的uuid
    $_stu_uuid = $studentIdToUuid[$item['student_id']] ?? '';
    if (empty($_stu_uuid)) {
        continue;
    }
    // 被推荐人对应的推荐人uuid
    $studentRef[$_stu_uuid] = $refUuidArr[$item['referee_id']];
}

$eventTypeList = [];
$time = time();
$erp = new Erp();
foreach ($pointsList as $points) {
    $status = ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE;
    $reason = '';
    // 待发放
    if ($points['status'] == ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING) {
        // 如果待发放的没有到期，则不发放
        $delayTime = $points['create_time'] + $points['delay'];

        if ($delayTime > $time) {
            continue;
        }

        // 获取任务类型
        if (!isset($eventTypeList[$points['event_task_id']])) {
            $eventTaskInfo = ErpEventTaskModel::getRecord(['id' => $points['event_task_id']]);
            $eventInfo = ErpEventModel::getRecord(['id' => $eventTaskInfo['event_id']]);
            $eventTypeList[$points['event_task_id']] = $eventInfo['type'];
        }

        //退费验证
        $verifyArr['type'] =$eventTypeList[$points['event_task_id']];
        $verifyArr['event_task_id'] =$points['event_task_id'];
        $verifyArr['uuid'] =$points['finish_task_uuid'];
        $verifyArr['app_id'] = Constants::SMART_APP_ID;
        $verify = CashGrantService::awardAndRefundVerify($verifyArr);
        if (!$verify) {
            SimpleLogger::info('script::auto_send_task_award_points', ['info' => 'refund verify not pass', 'param' => $points]);
            $status = ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED;
            $reason = ErpUserEventTaskAwardGoldLeafModel::REASON_RETURN_COST;
        }
    }
    $referrerUuid = $studentRef[$points['finish_task_uuid']] ?? '';
    // // 本条记录如果是给被推荐人发放奖励，说名完成人和获得奖励的人是同一个人， uuid = finish_task_uuid
    // if ($points['to'] == ErpEventTaskModel::AWARD_TO_BE_REFERRER){
    //     $referrerUuid = $points['uuid'];
    // }
    // var_dump(count($pointsList),$points['uuid'], $points['event_task_id'], $status, $points['id'], $referrerUuid, ['reason' => $reason]);exit;
    $taskResult = $erp->addEventTaskAward($points['uuid'], $points['event_task_id'], $status, $points['id'], $referrerUuid, ['reason' => $reason]);
    SimpleLogger::info("script::auto_send_task_award_points", [
        'params' => $points,
        'response' => $taskResult,
        'status' => $status,
        'referrer_uuid' => $referrerUuid,
    ]);
    // 积分发放成功后 把消息放入到 客服消息队列
    if (!empty($taskResult['data'])) {
        $pushMessageData = ['points_award_ids' => $taskResult['data']['points_award_ids']];
        (new PushMessageTopic())->pushWX($pushMessageData,PushMessageTopic::EVENT_PAY_TRIAL)->publish();
    }
}