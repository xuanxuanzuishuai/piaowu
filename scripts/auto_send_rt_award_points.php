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
use App\Models\OperationActivityModel;
use App\Models\RtActivityModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\Queue\PushMessageTopic;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// 获取到期发待发放和发放失败的积分列表, 只读取award_type 为空或者指定的award_node
$time = time();
$daytime = strtotime(date('Y-m-d'));
$whereTime = $daytime - 6 * Util::TIMESTAMP_ONEDAY;
$where = [
    'status' => [ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING, ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_FAIL],
    'award_node' => ['referral_rt_coupon'],
    'award_time[>=]' => $whereTime,
    'award_time[<]' => $daytime,
];
$pointsList= ErpUserEventTaskAwardGoldLeafModel::getRecords($where);
if (empty($pointsList)) {
    SimpleLogger::info("script::auto_send_rt_award_points", ['info' => "is_empty_points_list", 'where' => $where]);
    return 'success';
}

// 获取完成任务的人uuid 和 id
$studentUuid = array_column($pointsList, 'finish_task_uuid');
$studentIdList = DssStudentModel::getRecords(['uuid' => $studentUuid], ['id','uuid']);
$studentIdToUuid = array_column($studentIdList, 'uuid', 'id');
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

$erp = new Erp();
$refundTimeMap = [];
$arrBillId = array_values(array_unique(array_column($pointsList, 'bill_id')));
$batchBillId = array_chunk($arrBillId, 100);
foreach ($batchBillId as $billIds) {
    $refundInfo = $erp->getRefundTime($billIds);
    $refundTimes = $refundInfo['data']??[];
    foreach ($refundTimes as $billId => $refundTimee) {
        $arrRefundTime = array_column($refundTimee, 'refund_time');
        $refundTimeMap[$billId] = empty($arrRefundTime) ? 0 : min($arrRefundTime);
    }
}

$eventTypeList = [];
$erp = new Erp();
foreach ($pointsList as $points) {
    $status = ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE;
    $reason = '';
    // 待发放
    if ($points['status'] == ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING) {
        // 如果待发放的没有到期，则不发放
        $delayTime = $points['award_time'];
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
        //$verify = CashGrantService::awardAndRefundVerify($verifyArr);
        $verify = true;
        $billId = $points['bill_id'];
        //订单退款时间
        $refundTime = $refundTimeMap[$billId] ?? 0;
        SimpleLogger::info("script::auto_send_rt_award_points", ['refund_data' => $refundTime, 'delay_time' => $delayTime]);
        if ($refundTime > 0 && $refundTime <= $delayTime) {   //如果15天内退费,不发放奖励
            $verify = false;
        }
        $aInfo = RtActivityModel::getRecord(['activity_id' => $points['activity_id']], ['enable_status']);
        if (empty($aInfo) || $aInfo['enable_status'] == OperationActivityModel::ENABLE_STATUS_DISABLE) {   // RT活动状态已禁用
            $verify = false;
        }
        if (!$verify) {
            SimpleLogger::info('script::auto_send_rt_award_points', ['info' => 'refund verify not pass', 'param' => $points]);
            $status = ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED;
            $reason = ErpUserEventTaskAwardGoldLeafModel::REASON_RETURN_COST;
        }
    }
    $referrerUuid = $studentRef[$points['finish_task_uuid']] ?? '';
    $taskResult = $erp->addEventTaskAward($points['finish_task_uuid'], $points['event_task_id'], $status, $points['id'], $referrerUuid, ['reason' => $reason]);
    SimpleLogger::info("script::auto_send_rt_award_points", [
        'params' => $points,
        'response' => $taskResult,
        'status' => $status,
        'referrer_uuid' => $referrerUuid,
    ]);
    // 积分发放成功后 把消息放入到 客服消息队列
    if (!empty($taskResult['data'])) {
        $pushMessageData = ['points_award_ids' => $taskResult['data']['points_award_ids']];
        (new PushMessageTopic())->pushWX($pushMessageData, PushMessageTopic::EVENT_PAY_TRIAL)->publish(5);
    }
}
