<?php
namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '2048M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Erp;
use App\Libs\SimpleLogger;
use App\Models\CHModel\StudentAccountDetailModel;
use App\Models\Erp\ErpStudentAccountDetail;
use App\Models\Erp\ErpStudentModel;
use App\Models\UuidCreditModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// 每日需要更新最近90天获得 和 余额有变动的用户
$timeDiff = 7776000; // 86400 * 90

//最近一日时间范围
$yesterdayStartTime = strtotime(date('Ymd')) - 86400;

// 90天前的一日时间范围
$ninetyStartTime = $yesterdayStartTime - $timeDiff;

//此脚本每日更新金叶子当前余额和近90天新获得
//思路是： 余额变动的分为 昨日有变动 和 昨日有过期
//近90天新获得： 分为前第91天有获得 和 昨日有获得
//汇总上述用户，更新信息

//余额变动
$totalNumChangeStudent = StudentAccountDetailModel::getHasChangeStudentId($yesterdayStartTime, $yesterdayStartTime + 86400);

$ninetyDayNumChangeStudent = StudentAccountDetailModel::getHasChangeStudentId($ninetyStartTime, $ninetyStartTime + 86400, false);

//汇总需要重新计算的
$needUpdateStudentId = array_unique(array_merge(array_column($totalNumChangeStudent, 'student_id'), array_column($ninetyDayNumChangeStudent, 'student_id')));

$needUpdateStudent = array_column(ErpStudentModel::getRecords(['id' => $needUpdateStudentId], ['id', 'uuid']), 'uuid', 'id');

SimpleLogger::info('today execute ', ['total_num' => count($needUpdateStudent)]);

$endTime = strtotime(date('Ymd'));
$startTime = $endTime - $timeDiff;

$ckData = array_column(StudentAccountDetailModel::timeRangeOnlyAdd(array_keys($needUpdateStudent), $startTime, $endTime), 'total', 'student_id');

$erp = new Erp();
foreach ($needUpdateStudent as $erpStudentId => $uuid) {
    SimpleLogger::info('today execute start', ['uuid' => $uuid]);
    $lastGet = $ckData[$erpStudentId] ?? 0;
    $totalNum = array_column($erp->studentAccount($uuid)['data'], 'total_num', 'sub_type')[ErpStudentAccountDetail::SUB_TYPE_GOLD_LEAF] ?? 0;
    $info = UuidCreditModel::getRecord(['uuid' => $uuid]);
    if (!empty($info)) {
        UuidCreditModel::updateRecord($info['id'], ['last_get' => $lastGet, 'total_num' => $totalNum]);
    } else {
        UuidCreditModel::insertRecord(
            [
                'uuid' => $uuid,
                'last_get' => $lastGet,
                'total_num' => $totalNum
            ]
        );
    }
}