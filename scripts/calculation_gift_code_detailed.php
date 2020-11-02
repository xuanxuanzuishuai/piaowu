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
    $giftCodeCount = $db->count('gift_codey', ['apply_user[!]' => null]);
    //分批查询数据:每次查询1000条数据
    $pageCount = 1000;
    $forTimes = ceil($giftCodeCount / $pageCount);

    for ($i = 1; $i <= $forTimes; $i++) {
        $db->beginTransaction();
        $sql = "SELECT id, apply_user, bill_package_id, valid_units, valid_num, be_active_time, code_status, operate_time, package_v1
FROM gift_code WHERE apply_user IS NOT NULL order by id asc ";

        $sql .= Util::limitation($i, $pageCount);
        $giftCodeRecord = $db->queryAll($sql);
        //进行计算每个激活码的起始时间
        $batchInsertData = GiftCodeDetailedService::GiftCodeDataWashing($giftCodeRecord);

        $affectRows = GiftCodeDetailedModel::batchInsert($batchInsertData);

        if ($affectRows == 0) {
            $db->rollBack();
            SimpleLogger::error("gift_code_batch_insert_data_error ", ['affect_rows' => $affectRows, 'i' => $i]);
            return false;
        }
        $db->commit();
    }

} catch (Exception $e) {
    SimpleLogger::error($e->getMessage(), $msgBody ?? []);
    return false;
}
return true;


