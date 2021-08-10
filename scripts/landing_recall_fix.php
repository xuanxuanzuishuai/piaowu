<?php
/**
 * 只需要执行一次,landing_recall_log表增加字段,修复数据
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-08-10 11:26:00
 * Time: 10:10
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssMobileLogModel;
use App\Models\Dss\DssStudentModel;
use App\Models\LandingRecallLogModel;
use App\Models\LandingRecallModel;
use App\Services\LandingRecallService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

$db = MysqlDB::getDB();
$dbRO = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
$studentTable = DssStudentModel::getTableNameWithDb();

$id = 0;
while (true) {
    $sqlLandingRecallLog = "
        SELECT
            id,mobile,create_time
        FROM landing_recall_log WHERE id>{$id} AND landing_recall_id=0 ORDER BY id ASC LIMIT 0,1000;
    ";
    $recallLogs = $db->queryAll($sqlLandingRecallLog);
    if (empty($recallLogs)) {
        break;
    }
    $id = max(array_column($recallLogs, 'id'));
    foreach ($recallLogs as $recallLog) {
        $id = $recallLog['id'];
        $mobile = $recallLog['mobile'];
        $logCreateTime = $recallLog['create_time'];
        $studentSql = "select id,create_time from {$studentTable} where mobile='{$mobile}'";
        $si = $dbRO->queryAll($studentSql);
        if ($si) {
            $createTime = $si[0]['create_time'];
            if ($logCreateTime > $createTime) {
                LandingRecallLogModel::updateRecord($id, ['landing_recall_id' => 2]);
                echo 'log_id=' . $id . 'recall_id=2' . PHP_EOL;
            } else {
                LandingRecallLogModel::updateRecord($id, ['landing_recall_id' => 1]);
                echo 'log_id=' . $id . 'recall_id=1' . PHP_EOL;
            }
        } else {
            LandingRecallLogModel::updateRecord($id, ['landing_recall_id' => 1]);
            echo 'log_id=' . $id . 'recall_id=1' . PHP_EOL;
        }
    }
}

echo 'finish' . PHP_EOL;
