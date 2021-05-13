<?php
/**
 * 发放购买体验课的奖励
 * 每日18点执行，脚本修改时间后需要重新测试
 */

namespace App;


use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssAiPlayRecordCHModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Services\Queue\PushMessageTopic;
use Dotenv\Dotenv;

// 1小时超时
set_time_limit(3600);

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// create_time 转换指定的时间格式 - 这里去掉分和秒
$formatTime = "Y-m-d H:00:00";
// 查询数据的范围  14天内的数据， 因为 1号19点购买的可以再13号19点练琴(那就是14号才能处理)
$whereTime = strtotime(date($formatTime)) - 14 * Util::TIMESTAMP_ONEDAY;

// 获取到期发待发放和发放失败的积分列表, 只读invite_stage=1 体验卡奖励
$where = [
    'status' => [ErpUserEventTaskAwardGoldLeafModel::STATUS_WAITING, ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE_FAIL],
    'award_node' => ErpUserEventTaskAwardGoldLeafModel::AWARD_NODE_BUY_TRIAL,
    'create_time[>=]' => $whereTime
];
$pointsList = ErpUserEventTaskAwardGoldLeafModel::getRecords($where);
if (empty($pointsList)) {
    SimpleLogger::info("script::auto_send_task_award_points", ['info' => "is_empty_points_list", 'where' => $where]);
    return 'success';
}
$erp = new Erp();
$time = time();
foreach ($pointsList as $points) {
    $status = ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE;
    $reason = '';

    // 计算时间  按小时计算
    $createHour = strtotime(date($formatTime, $points['create_time']));
    // 任务完成截止时间
    $taskLastTime = intval($createHour + $points['delay']);
    if ($taskLastTime < $time) {
        // 超过12天不发放
        $status = ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED;
        $reason = ErpUserEventTaskAwardGoldLeafModel::REASON_NO_PLAY;
    }else {
        // 12天内没有练琴记录本次不发放
        // 计算是否有练琴记录
        $studentInfo = DssStudentModel::getRecord(['uuid' => $points['finish_task_uuid']], ['id']);
        $aiPlayList = DssAiPlayRecordCHModel::getStudentBetweenTimePlayRecord((int)$studentInfo['id'], (int)$points['create_time'], $taskLastTime);
        SimpleLogger::info("script::auto_send_buy_trial_award_points",['info'=>'aiplay','data'=> $aiPlayList]);
        // 没有练琴记录-本次不处理该条奖励
        if (count($aiPlayList) <= 0) {
            continue;
        }
    }
    // var_dump($points['finish_task_uuid'], $points['event_task_id'], $status, $points['id'], $points['finish_task_uuid'], ['reason' => $reason]);exit;
    $taskResult = $erp->addEventTaskAward($points['finish_task_uuid'], $points['event_task_id'], $status, $points['id'], $points['uuid'], ['reason' => $reason]);
    SimpleLogger::info("script::auto_send_buy_trial_award_points", [
        'params' => $points,
        'response' => $taskResult,
        'status' => $status,
        'referrer_uuid' => $points['finish_task_uuid'],
    ]);
    // 积分发放成功后 把消息放入到 客服消息队列
    if (!empty($taskResult['data'])) {
        $pushMessageData = ['points_award_ids' => $taskResult['data']['points_award_ids']];
        (new PushMessageTopic())->pushWX($pushMessageData, PushMessageTopic::EVENT_PAY_TRIAL)->publish(5);
    }
}
