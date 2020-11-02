<?php

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

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
try {
    $db = MysqlDB::getDB();
    $giftCodeDetailed = $db->queryAll('select * from gift_code_detailed group by apply_user');
    $giftCodeDetailedCount = count($giftCodeDetailed);
    //分批查询数据:每次查询1000条数据
    $pageCount = 1000;
    $forTimes = ceil($giftCodeDetailedCount / $pageCount);

    for ($i = 1; $i <= $forTimes; $i++) {
        $db->beginTransaction();
        //查询激活码数据
        $giftCodeDetailedSql = "select * from (
                select id, gift_code_id, apply_user, code_start_date, code_end_date, package_type, valid_days, create_time, status, actual_days,
                 ROW_NUMBER() OVER(PARTITION BY apply_user ORDER BY code_end_date DESC) r from gift_code_detailed
                 ) gcd where r = 1 group by apply_user order by id asc ";
        $giftCodeDetailedSql .= Util::limitation($i, $pageCount);
        $giftCodeDetailedInfo = $db->queryAll($giftCodeDetailedSql);
        //查询学生数据
        $studentIds = array_column($giftCodeDetailedInfo, 'apply_user');
        $studentSql = "select * from student where id in (" . implode(',', $studentIds) .')';
        $studentInfo = $db->queryAll($studentSql);
        $studentInfo = array_combine(array_column($studentInfo, 'id'), $studentInfo);

        //验证学生最后一个激活码的结束时间是否于student内的sub_end_date相等
        $batchInsertData = GiftCodeDetailedService::GiftCodeEndDateVerification($giftCodeDetailedInfo, $studentInfo);
        GiftCodeDetailedModel::batchInsert($batchInsertData);
        $db->commit();
    }

} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;


