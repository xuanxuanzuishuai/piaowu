<?php
namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');
//获取参数
$cliParams = getopt('p:c:');
$page = $cliParams['p'];
$pageCount = $cliParams['c'];

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
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
    $db->beginTransaction();
    //查询激活码数据
    $giftCodeDetailedSql = "select * from (
            select id, gift_code_id, apply_user, code_start_date, code_end_date, package_type, valid_days, create_time, status, actual_days,
             ROW_NUMBER() OVER(PARTITION BY apply_user ORDER BY code_end_date DESC) r from dss_dev.gift_code_detailed where status = 1
             ) gcd where r = 1 order by id asc ";
    $giftCodeDetailedSql .= Util::limitation($page, $pageCount);
    $giftCodeDetailedInfo = $db->queryAll($giftCodeDetailedSql, [':status' => Constants::STATUS_TRUE]);

    //查询学生数据
    $studentIds = array_column($giftCodeDetailedInfo, 'apply_user');
    $studentStrIds = implode(',', $studentIds);
    $studentSql = "select * from student where id in ($studentStrIds)";
    $studentInfo = $db->queryAll($studentSql);
    $studentInfo = array_combine(array_column($studentInfo, 'id'), $studentInfo);

    $batchInsertData = GiftCodeDetailedService::GiftCodeEndDateVerification($giftCodeDetailedInfo, $studentInfo);
    $affectRows = GiftCodeDetailedModel::batchInsert($batchInsertData);
    if ($affectRows == 0) {
        $db->rollBack();
        SimpleLogger::error("washing_gift_code_batch_insert_data_error ", ['affect_rows' => $affectRows, 'i' => $page]);
        return false;
    }
    $db->commit();

} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;


