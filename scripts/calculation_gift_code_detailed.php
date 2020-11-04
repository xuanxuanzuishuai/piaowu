<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\RedisDB;
use App\Models\GiftCodeDetailedModel;
use App\Services\GiftCodeDetailedService;
use Dotenv\Dotenv;
use App\Libs\Util;
use App\Libs\SimpleLogger;
use App\Libs\MysqlDB;
use Exception;


$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$redis = RedisDB::getConn();
//获取参数
$cliParams = getopt('p:c:');
$page = $cliParams['p'];
$pageCount = $cliParams['c'];
//获取新产品包
$packageV1Date = $redis->get('washing_package_v1_data');
$packageV1Date = json_decode($packageV1Date,true);
//获取旧产品包
$packageDate = $redis->get('washing_package_data');
$packageDate = json_decode($packageDate,true);
try {
    $db = MysqlDB::getDB();
    $db->beginTransaction();

    //查询所有激活码
    $sql = "SELECT id, apply_user, bill_package_id, valid_units, valid_num, be_active_time, code_status, operate_time, package_v1
FROM gift_code WHERE apply_user IS NOT NULL order by id asc ";

    $sql .= Util::limitation($page, $pageCount);
    $giftCodeRecord = $db->queryAll($sql);
    $studentArrIds = array_unique(array_column($giftCodeRecord, 'apply_user'));
    $studentStrIds = implode(',', $studentArrIds);

    //获取用户的信息
    $studentSql = "select id from student where id in ($studentStrIds)";
    $studentInfo = $db->queryAll($studentSql);
    $studentInfo = array_combine(array_column($studentInfo, 'id'), $studentInfo);
    //获取每个用户的最后一条激活码数据
    $studentsLastCodeDetailedSql = "select * from (
            select id, gift_code_id, apply_user, code_start_date, code_end_date, package_type, valid_days, create_time, status, actual_days,
             ROW_NUMBER() OVER(PARTITION BY apply_user ORDER BY code_end_date DESC) r from gift_code_detailed
             where apply_user in ($studentStrIds)
             ) gcd where r = 1";
    $studentsLastCodeDetailedInfo = $db->queryAll($studentsLastCodeDetailedSql);
    $studentsLastCodeDetailedInfo = array_combine(array_column($studentsLastCodeDetailedInfo, 'apply_user'), $studentsLastCodeDetailedInfo);

    //进行计算每个激活码的起始时间
   $batchInsertData = GiftCodeDetailedService::GiftCodeDataWashing($giftCodeRecord, $studentInfo, $studentsLastCodeDetailedInfo, $packageV1Date, $packageDate);

    $affectRows = GiftCodeDetailedModel::batchInsert($batchInsertData);

    if ($affectRows == 0) {
        $db->rollBack();
        SimpleLogger::error("gift_code_batch_insert_data_error ", ['affect_rows' => $affectRows, 'i' => $page]);
        return false;
    }
    $db->commit();
} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;


