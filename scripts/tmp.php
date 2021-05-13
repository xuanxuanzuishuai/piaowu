<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/29
 * Time: 15:38
 */

namespace App;

date_default_timezone_set('PRC');
set_time_limit(0);
ini_set('memory_limit', '1024M');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Models\AgentAwardBillExtModel;
use Dotenv\Dotenv;
$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
/**
 * 临时刷数据脚本
 */
SimpleLogger::info('tmp agent award bill ext brush package type data start', []);
$db = MysqlDB::getDB();
//获取当前数据库中所有的需要刷数据的订单号
$agentAwardBillExtData = AgentAwardBillExtModel::getRecords(['package_type'=>0],['parent_bill_id']);
if (empty($agentAwardBillExtData)) {
    SimpleLogger::info('tmp agent award bill ext brush package type data empty', []);
    return true;
}

$billIds = array_column($agentAwardBillExtData,'parent_bill_id');

$ids    = array_chunk($billIds, 200);
foreach ($ids as $ids200) {

    $sql = 'SELECT
            ext->>\'$.package_type\' as package_type,
            ext_parent_bill_id
            FROM
            agent_award_detail
        WHERE
            ext_parent_bill_id in ('.implode(',',$ids200).');';

    $agentAwardBillData = $db->queryAll($sql);

    if (empty($agentAwardBillData)) {
        SimpleLogger::info('agent award bill brush package type data empty', $ids200);
        continue;
    }

    $type_1 = $type_2 = [];

    foreach ($agentAwardBillData as $value){
        if($value['package_type'] == 1){
            $type_1[] = $value['ext_parent_bill_id'];
        }elseif ($value['package_type'] == 2){
            $type_2[] = $value['ext_parent_bill_id'];
        }
    }

    if (!empty($type_1)){
        $res = AgentAwardBillExtModel::batchUpdateRecord(['package_type' => 1],['parent_bill_id' =>$type_1]);
        if ($res){
            SimpleLogger::info('update bill ext data success', ['res' => $res, 'parent_bill_id' => $type_1]);
        }else{
            SimpleLogger::info('update bill ext data fail', ['res' => $res, 'parent_bill_id' => $type_1]);
        }
    }

    if (!empty($type_2)){
        $res = AgentAwardBillExtModel::batchUpdateRecord(['package_type' => 2],['parent_bill_id' =>$type_2]);
        if ($res){
            SimpleLogger::info('update bill ext data success', ['res' => $res, 'parent_bill_id' => $type_2]);
        }else{
            SimpleLogger::info('update bill ext data fail', ['res' => $res, 'parent_bill_id' => $type_2]);
        }
    }

}

SimpleLogger::info('tmp agent award bill ext brush package type data end', []);
