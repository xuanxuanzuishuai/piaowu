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
use App\Models\Dss\DssPackageExtModel;
use App\Models\StudentReferralStudentStatisticsModel;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$now = time();
/**
 * 补全奖励订单的额外信息agent_award_bill_ext数据
 */
SimpleLogger::info('agent award bill ext make up start', []);
$db = MysqlDB::getDB();
//获取当前数据库中已存在的奖励订单数据
$sql = 'SELECT
            aw.ext_parent_bill_id,
            aw.agent_id,
            aw.student_id,
            aw.ext->>\'$.package_type\' as package_type,
            abm.id as "bill_map_id"
            FROM
            agent_award_detail AS aw
            INNER JOIN agent AS a ON a.id = aw.agent_id
            LEFT JOIN  agent_bill_map as abm on aw.ext_parent_bill_id=abm.bill_id
            LEFT JOIN agent_award_bill_ext AS ab ON aw.ext_parent_bill_id = ab.parent_bill_id 
        WHERE
            aw.action_type != 3 
            AND ab.id IS NULL;';
$agentAwardBillData = $db->queryAll($sql);
if (empty($agentAwardBillData)) {
    SimpleLogger::info('agent award bill ext make up data empty', []);
    return true;
}
//获取学生的转介绍推荐人数据
$studentIds = array_column($agentAwardBillData, 'student_id');
$studentReferralData = StudentReferralStudentStatisticsModel::getRecords(['student_id' => $studentIds], ['student_id', 'referee_id']);
$studentReferralData = array_column($studentReferralData, null, 'student_id');

//判断订单是否是学生与代理建立绑定关系后的首单
$studentFirstOrder = [];
$agentAwardBillFormatData = array_map(function (&$val) use (&$studentFirstOrder) {
    if ($val['package_type'] == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
        $val['is_first_order'] = AgentAwardBillExtModel::IS_FIRST_ORDER_YES;
    } elseif (($val['package_type'] == DssPackageExtModel::PACKAGE_TYPE_NORMAL) && empty($studentFirstOrder[$val['student_id']][$val['agent_id']])) {
        $val['is_first_order'] = AgentAwardBillExtModel::IS_FIRST_ORDER_YES;
        $studentFirstOrder[$val['student_id']][$val['agent_id']] = true;
    }
    return $val;
}, $agentAwardBillData);

//组合数据
$agentAwardBillExtData = [];
foreach ($agentAwardBillFormatData as $k => $v) {
    $agentAwardBillExtData[] = [
        'student_id' => $v['student_id'],
        'parent_bill_id' => $v['ext_parent_bill_id'],
        'package_type' => $v['package_type'],
        'student_referral_id' => empty($studentReferralData[$v['student_id']]) ? 0 : $studentReferralData[$v['student_id']]['referee_id'],
        'own_agent_id' => $v['agent_id'],
        'own_agent_status' => 1,
        'signer_agent_id' => $v['agent_id'],
        'signer_agent_status' => 1,
        'is_hit_order' => empty($studentReferralData[$v['student_id']]) ? 2 : 1,
        'is_first_order' => empty($v['is_first_order']) ? AgentAwardBillExtModel::IS_FIRST_ORDER_NO : $v['is_first_order'],
        'is_agent_channel_buy' => empty($v['bill_map_id']) ? 2 : 1,
        'create_time' => $now,
    ];
}
//批量写入数据
$res = AgentAwardBillExtModel::batchInsert($agentAwardBillExtData);
SimpleLogger::info('insert bill ext data', ['res' => $res, $agentAwardBillExtData]);
SimpleLogger::info('agent award bill ext make up end', []);
