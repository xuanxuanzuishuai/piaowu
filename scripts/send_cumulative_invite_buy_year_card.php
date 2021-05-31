<?php
/**
 * 发放购买年卡累计奖励
 * 每天18:30执行
 */


namespace App;

use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\Erp\ErpUserEventTaskModel;
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

// 获取当日16天前所在的月到月末给推荐人发放成功的奖励信息 （例： 4.30号21点购买的应该在16号18点发放）
// 16号18点上一个月所有20000积分的奖励应该全部发放完成，所有16号18:30应该处理完上个月所有累计奖励
$monthOneDay = date('Y-m-01 00:00:00', strtotime(" -16 day"));
$nextMonthOneDay = date("Y-m-01 00:00:00", strtotime("$monthOneDay + 1 month"));
SimpleLogger::info("script::send_cumulative_invite_buy_year_card", ['info' => "start", 'monthOneDay' => $monthOneDay, 'nextMonthOneDay' => $nextMonthOneDay]);
$yearCardTask = json_decode(DictConstants::get(DictConstants::REFERRAL_CONFIG, 'normal_task_config'), true);
$where = [
    'start_time' => strtotime($monthOneDay),
    'end_time' => strtotime($nextMonthOneDay),
    'status' => ErpUserEventTaskAwardGoldLeafModel::STATUS_GIVE,
    'to' => ErpEventTaskModel::AWARD_TO_REFERRER,
    'package_type' => DssPackageExtModel::PACKAGE_TYPE_NORMAL,
    'event_task_id' => array_values($yearCardTask),
];
$list = ErpUserEventTaskAwardGoldLeafModel::getStudentAwardList($where, 'group by uuid');
if (empty($list)) {
    SimpleLogger::info("script::send_cumulative_invite_buy_year_card", ['info' => "is_empty_points_list", 'where' => $where]);
    return 'success';
}

/** 获取推荐人在指定的月份已经获取到的所有累计邀请奖励 start */
$currDay = date("d");
if ($currDay <= 16) {
    // 16号发放的40000累计邀请奖励都是发放的上一个月的
    $refAwardStartTime = strtotime(date('Y-m-01 00:00:00'));
    $refAwardEndTime = strtotime(date("Y-m-01 00:00:00", strtotime(" + 1 month")));
} else {
    // 17号才开始处理本月累计邀请，所有发放累计邀请奖励记录创建时间是从17号开始
    $refAwardStartTime = strtotime(date('Y-m-17 00:00:00'));
    $refAwardEndTime = strtotime(date("Y-m-01 00:00:00", strtotime(" + 1 month")));
}
$refAwardWhere = [
    'create_time[>=]' => $refAwardStartTime,
    'create_time[<]' => $refAwardEndTime,
    'uuid' => array_column($list, 'uuid'),
    'award_node' => ErpUserEventTaskAwardGoldLeafModel::AWARD_NODE_CUMULATIVE_INVITE_BUY_YEAR,
];

$refAwardList = ErpUserEventTaskAwardGoldLeafModel::getRecords($refAwardWhere, ['uuid']);
$refCumulativeNum= [];
foreach ($refAwardList as $item) {
    if (isset($refCumulativeNum[$item['uuid']])) {
        $refCumulativeNum[$item['uuid']] +=1;
    } else {
        $refCumulativeNum[$item['uuid']]=1;
    }
}
/** 获取推荐人在指定的月份已经获取到的所有累计邀请奖励 end */
SimpleLogger::info("script::auto_send_buy_trial_award_points ", [$currDay,$refAwardWhere,$refAwardList,$refCumulativeNum]);
$taskId = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'cumulative_invite_buy_year_card');
$erp = new Erp();
// 奖励阶段数 - 没满足这个数量应发奖励+1
$awardStageNum = 3;
foreach ($list as $award) {
    // 如果count小于3，不需要发放奖励
    if ($award['total'] < $awardStageNum) {
        continue;
    }
    // 计算应发奖励总数
    $awardNum = intval($award['total']/$awardStageNum);
    // 应发奖励数不大于已发奖励数说明奖励已发放 - 不发奖励
    if ($awardNum <= $refCumulativeNum[$award['uuid']]) {
        continue;
    }

    // 发放奖励
    for ($i = $refCumulativeNum[$award['uuid']]; $i < $awardNum; $i++) {
        $status = ErpUserEventTaskModel::EVENT_TASK_STATUS_COMPLETE;
        $reason = '';
        $taskResult = $erp->addEventTaskAward($award['finish_task_uuid'], $taskId, $status, 0, $award['uuid'], ['reason' => $reason]);
        SimpleLogger::info("script::auto_send_buy_trial_award_points", [
            'params' => $award,
            'response' => $taskResult,
            'status' => $status,
            'referrer_uuid' => $award['finish_task_uuid'],
        ]);
    }
}