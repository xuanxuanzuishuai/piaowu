<?php
/**
 * 临时脚本 - 手动执行一次
 * 给指定用户发放金叶子到待发放列表
 */

namespace App;

use App\Libs\Constants;
use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use App\Services\ErpReferralService;
use App\Services\ErpUserService;
use Dotenv\Dotenv;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

/*********************** 需要替换的数据 start ***************/
$listStr = '
374289566795843675,1000
';
// 奖励金额对应的任务id
$awardTaskIdArr = [
    1000 => 723,
    4000 => 724,
    9000 => 725,
];
$planNum      = 1000; // 预计发放叶子总数
$planPeople   = 1;     // 预计发放总人数
$logTitle     = '脚本补发红包对应的金叶子-20210910041800';    // 日志信息
/*************** 需要替换的数据 end *****************************/


$successUuid = [];  // 操作成功的uuid
$failUuid    = [];  // 操作失败的uuid
// 解析数据
$studentList      = explode("\n", $listStr);
$studentUuidAward = [];
foreach ($studentList as $item) {
    if (empty($item)) {
        continue;
    }
    $_student_award                       = explode(",", $item);
    $studentUuidAward[$_student_award[0]] = $_student_award[1];
}
unset($item);

// 检查人数和预计总量是否一致
if (count($studentUuidAward) != $planPeople || array_sum($studentUuidAward) != $planNum) {
    SimpleLogger::info($logTitle, [
        "msg"         => '实际计算人数和数量与预计总量不符，不予执行',
        'plan_num'    => $planNum,
        'plan_people'    => $planPeople,
        'student_num' => count($studentUuidAward),
        'award_num'   => array_sum($studentUuidAward),
    ]);
    var_dump([
        "msg"         => '实际计算人数和数量与预计总量不符，不予执行',
        'plan_num'    => $planNum,
        'plan_people'    => $planPeople,
        'student_num' => count($studentUuidAward),
        'award_num'   => array_sum($studentUuidAward),
    ]);
    echo "FAIL" . PHP_EOL;
    exit;
}
SimpleLogger::info($logTitle, [
    'msg'         => '实际操作数量和人数与预计的一致，可以执行',
    'student_num' => count($studentUuidAward),
    'award_num'   => array_sum($studentUuidAward),
    'plan_num'    => $planNum,
    'plan_people'    => $planPeople,
]);

// 发放待发放积分
foreach ($studentUuidAward as $_uuid => $_awardNum) {
    $awardTaskId = $awardTaskIdArr[$_awardNum] ?? 0;
    if (empty($awardTaskId)) {
        $failUuid[] = ['uuid' => $_uuid, 'award' => $_awardNum, 'msg' => '奖励数量没有找到对应的任务id'];
        SimpleLogger::info('script::tmp_send_gold_left', [$_awardNum]);
        continue;
    }
    $res = (new Erp())->addEventTaskAward($_uuid, $awardTaskId, ErpReferralService::EVENT_TASK_STATUS_UNCOMPLETE);
    if (empty($res['data'])) {
        $failUuid[] = ['uuid' => $_uuid, 'award' => $_awardNum, 'msg' => '生成待发放奖励失败'];
        SimpleLogger::error('ERP_CREATE_USER_EVENT_TASK_AWARD_FAIL', [$_awardNum]);
        continue;
    }
    $successUuid[] = ['uuid' => $_uuid, 'award' => $_awardNum, 'msg' => "SUCCESS"];
}

/** 数据输出 */
if (!empty($failUuid)) {
    foreach ($failUuid as $item) {
        echo $item['uuid'] . "," . $item['award'] . "," . $item['msg'] . PHP_EOL;
    }
    unset($item);
}
if (!empty($successUuid)) {
    foreach ($successUuid as $item) {
        echo $item['uuid'] . "," . $item['award'] . "," . PHP_EOL;
    }
    unset($item);
}

echo "本次成功操作总次数," . count($successUuid) . PHP_EOL;
echo "本次操作失败总次数," . count($failUuid) . PHP_EOL;
