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
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpStudentAccountDetail;
use App\Models\Erp\ErpStudentModel;
use App\Models\UuidCreditModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

// 全量智能用户自己的 最近90天的金叶子获得 和 金叶子余额
$timeDiff = 7776000; // 86400 * 90
$endTime = strtotime(date('Ymd'));
$startTime = $endTime - $timeDiff;

$totalCount = DssStudentModel::getRecord(['ORDER' => ['id' => 'DESC']], 'id');
$limit = 10000;
$ceil = ceil($totalCount / $limit);
$erp = new Erp();

for($i = 0; $i < $ceil; $i++) {
    SimpleLogger::info('total execute start', ['i' => $i]);
    $idMin = $i * $limit;
    $idMAx = $idMin + $limit;
    $dssStudent = DssStudentModel::getRecords(['id[>]' => $idMin, 'id[<=]' => $idMAx], ['uuid']);
    if (!empty($dssStudent)) {
        $erpStudent = ErpStudentModel::getRecords(['uuid' => array_column($dssStudent, 'uuid')], ['id', 'uuid']);

        $erpStudentUUidRelate = array_column($erpStudent, 'uuid', 'id');
        //ck数据
        $ckData = array_column(StudentAccountDetailModel::timeRangeOnlyAdd(array_keys($erpStudentUUidRelate), $startTime, $endTime), 'total', 'student_id');

        if (!empty($erpStudentUUidRelate)) {
            foreach ($erpStudentUUidRelate as $erpStudentId => $uuid) {
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
        }
    }
    sleep(1);

}