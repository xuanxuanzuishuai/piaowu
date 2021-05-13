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
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

// require composer autoload
require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\DictConstants;
use App\Libs\MysqlDB;
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Libs\Spreadsheet;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AgentAwardBillExtModel;
use App\Models\AgentModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Services\AgentService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();
$startMemory = memory_get_usage();
$execsStartTime = time();
$excelData = $tmpFileNameList = [];
$tmpFileSavePath = '';
//附件保存路径
$attachmentFilePath = $_ENV['STATIC_FILE_SAVE_PATH'];
//设置表头
$excelTitle = [
    '订单号',
    '学员名称',
    '学员UUID',
    '学员手机号',
    '购买产品包',
    '支付时间',
    '支付状态',
    '推荐人',
    '绑定的一级代理',
    '绑定的一级代理ID',
    '绑定的二级代理',
    '绑定的二级代理ID',
    '绑定的代理模式',
    '成单的一级代理',
    '成单的一级代理ID',
    '成单的二级代理',
    '成单的二级代理ID',
    '成单的代理模式',
];
/**
 * 撞单数据:每周一早上11点发送邮件，统计上周的数据及总数据
 */
SimpleLogger::info('agent hit bill start', []);
list($startTime, $endTime) = Util::getDateWeekStartEndTime(strtotime("-1 week"));

//获取测试使用代理商ID列表，查询数据将此类数据排除在外
$companyTestAgentIdsDict = DictConstants::getSet(DictConstants::COMPANY_TEST_AGENT_IDS);
$companyTestAgentIds = [];
if (!empty($companyTestAgentIdsDict[0])) {
    foreach (explode(',', $companyTestAgentIdsDict[0]) as $ck => $cv) {
        $companyTestAgentIds[] = (int)$cv;
    }
}

//历史撞单总数
$historyHitCount = AgentAwardBillExtModel::getCount(
    [
        'is_hit_order[!]' => AgentAwardBillExtModel::IS_HIT_ORDER_NO,
        'signer_agent_id[!]' => $companyTestAgentIds,
        'own_agent_id[!]' => $companyTestAgentIds,
    ]);

if (!empty($historyHitCount)) {
    //历史转介绍与代理撞单订单数
    $historyReferralAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [

            'is_hit_order' => [
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_BIND_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_SIGNER_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_AND_BIND_AGENT_AND_SIGNER_AGENT,
            ],
            'signer_agent_id[!]' => $companyTestAgentIds,
            'own_agent_id[!]' => $companyTestAgentIds
        ]);
    //历史代理与代理撞单的订单数
    $historyAgentAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            'is_hit_order' => [
                AgentAwardBillExtModel::IS_HIT_ORDER_AGENT_HIT_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_AND_BIND_AGENT_AND_SIGNER_AGENT,
            ],
            'signer_agent_id[!]' => $companyTestAgentIds,
            'own_agent_id[!]' => $companyTestAgentIds

        ]);


    //上周撞单总数
    $lastWeekHitCount = AgentAwardBillExtModel::getCount(
        [
            "create_time[<>]" => [$startTime, $endTime],
            'is_hit_order[!]' => AgentAwardBillExtModel::IS_HIT_ORDER_NO,
            'signer_agent_id[!]' => $companyTestAgentIds,
            'own_agent_id[!]' => $companyTestAgentIds
        ]);
    //上周转介绍与代理撞单订单数
    $lastWeekReferralAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [

            "create_time[<>]" => [$startTime, $endTime],
            'is_hit_order' => [
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_BIND_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_SIGNER_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_AND_BIND_AGENT_AND_SIGNER_AGENT,
            ],
            'signer_agent_id[!]' => $companyTestAgentIds,
            'own_agent_id[!]' => $companyTestAgentIds
        ]);
    //上周代理与代理撞单的订单数
    $lastWeekAgentAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            "create_time[<>]" => [$startTime, $endTime],
            'is_hit_order' => [
                AgentAwardBillExtModel::IS_HIT_ORDER_AGENT_HIT_AGENT,
                AgentAwardBillExtModel::IS_HIT_ORDER_REFERRAL_HIT_AND_BIND_AGENT_AND_SIGNER_AGENT,
            ],
            'signer_agent_id[!]' => $companyTestAgentIds,
            'own_agent_id[!]' => $companyTestAgentIds
        ]);
    if (empty($historyHitCount)) {
        return true;
    }
    //分批次获取一次500条
    $pageGetCount = 500;
    $totalOrderData = [];
    $companyTestAgentIdsStr = implode(',', $companyTestAgentIds);
    $db = MysqlDB::getDB();
    $listSql = "SELECT
                IF
                    ( a.parent_id = 0, ab.agent_id, a.parent_id ) AS first_agent_id,
                IF
                    ( a.parent_id = 0, 0, ab.agent_id ) AS second_agent_id,
                    ab.id,
                    ab.ext ->> '$.parent_bill_id' AS parent_bill_id,
                    ab.ext ->> '$.division_model' AS division_model,
                    ab.ext ->> '$.agent_type' AS agent_type,
                    ab.student_id,
                    ab.is_bind,
                    bex.signer_agent_id,
                    bex.is_first_order,
                    bex.student_referral_id 
                FROM
                    agent_award_bill_ext AS bex
                    INNER JOIN agent_award_detail AS ab ON ab.ext_parent_bill_id = bex.parent_bill_id
                    INNER JOIN agent AS a ON ab.agent_id = a.id 
                WHERE
                    bex.is_hit_order != " . AgentAwardBillExtModel::IS_HIT_ORDER_NO . " 
                    AND bex.signer_agent_id NOT IN ( " . $companyTestAgentIdsStr . " ) 
                    AND bex.own_agent_id NOT IN ( " . $companyTestAgentIdsStr . " )
                ORDER BY
                    bex.id DESC";
    for ($i = 1; $i <= ceil($historyHitCount / $pageGetCount); $i++) {
        $orderData = $db->queryAll($listSql . " LIMIT " . ($i - 1) * $pageGetCount . "," . $pageGetCount);
        $totalOrderData = array_merge($totalOrderData, $orderData);
    }
    //查询订单的详细数据
    $giftCodeDetail = array_column(DssGiftCodeModel::getGiftCodeDetailByBillId(array_column($totalOrderData, 'parent_bill_id')), null, 'parent_bill_id');
    //查询成单代理商的数据&查询归属代理商数据
    $recommendBillsSignerAgentIdArr = array_column($totalOrderData, 'signer_agent_id');
    $recommendBillsFirstAgentIdArr = array_column($totalOrderData, 'first_agent_id');
    $recommendBillsSecondAgentIdArr = array_column($totalOrderData, 'second_agent_id');
    $recommendBillsAgentData = AgentModel::getAgentParentData(array_unique(array_merge($recommendBillsSignerAgentIdArr, $recommendBillsSecondAgentIdArr, $recommendBillsFirstAgentIdArr)));
    if (!empty($recommendBillsAgentData)) {
        $recommendBillsAgentData = array_column($recommendBillsAgentData, null, 'id');
    }
    //查询学生推荐人数据
    $studentReferralIds = array_column($totalOrderData, 'student_referral_id');
    $studentReferralData = array_column(DssStudentModel::getRecords(['id' => array_unique($studentReferralIds)], ['id', 'name']), null, 'id');
    //组合课包数据以及成单人数据
    foreach ($totalOrderData as $rsk => &$rsv) {
        $rsv['signer_first_agent_name'] = $rsv['signer_second_agent_name'] = $rsv['signer_first_agent_id'] = $rsv['signer_second_agent_id'] = $rsv['signer_agent_type'] = "";
        if (!empty($rsv['signer_agent_id'])) {
            $rsv['signer_first_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['name'];
            $rsv['signer_second_agent_name'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['name'] : "";
            $rsv['signer_first_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['p_id'] : $recommendBillsAgentData[$rsv['signer_agent_id']]['id'];
            $rsv['signer_second_agent_id'] = !empty($recommendBillsAgentData[$rsv['signer_agent_id']]['p_id']) ? $recommendBillsAgentData[$rsv['signer_agent_id']]['id'] : "";
            $rsv['signer_agent_type'] = $recommendBillsAgentData[$rsv['signer_agent_id']]['agent_type'];
        }
        $rsv['bill_package_id'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_package_id'];
        $rsv['bill_amount'] = $giftCodeDetail[$rsv['parent_bill_id']]['bill_amount'];
        $rsv['code_status'] = $giftCodeDetail[$rsv['parent_bill_id']]['code_status'];
        $rsv['buy_time'] = $giftCodeDetail[$rsv['parent_bill_id']]['buy_time'];
        $rsv['package_name'] = $giftCodeDetail[$rsv['parent_bill_id']]['package_name'];
        //归属人
        $rsv['first_agent_name'] = !empty($recommendBillsAgentData[$rsv['first_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['first_agent_id']]['parent_name'] : $recommendBillsAgentData[$rsv['first_agent_id']]['name'];
        $rsv['second_agent_name'] = !empty($recommendBillsAgentData[$rsv['second_agent_id']]['parent_name']) ? $recommendBillsAgentData[$rsv['second_agent_id']]['name'] : "";
        $rsv['second_agent_id_true'] = empty($rsv['second_agent_id']) ? '' : $rsv['second_agent_id'];
        $rsv['app_id'] = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        //学生转介绍人名称
        $rsv['student_referral_name'] = empty($rsv['student_referral_id']) ? '' : $studentReferralData[$rsv['student_referral_id']]['name'];
    }
    $totalOrderData = AgentService::formatRecommendBillsData(['list' => $totalOrderData]);
    //撞单的订单明细表字段
    foreach ($totalOrderData['list'] as $ek => $ev) {
        $excelData[] = [
            'parent_bill_id' => $ev['parent_bill_id'],
            'student_name' => $ev['student_name'],
            'student_uuid' => $ev['student_uuid'],
            'student_mobile' => $ev['student_mobile'],
            'package_name' => $ev['package_name'],
            'buy_time' => date("Y-m-d H:i:s", $ev['buy_time']),
            'code_status_name' => $ev['code_status_name'],
            'student_referral_id' => $ev['student_referral_name'],
            'first_agent_name' => $ev['first_agent_name'],
            'first_agent_id' => $ev['first_agent_id'],
            'second_agent_name' => $ev['second_agent_name'],
            'second_agent_id_true' => $ev['second_agent_id_true'],
            'type_name' => $ev['type_name'],
            'signer_first_agent_name' => $ev['signer_first_agent_name'],
            'signer_first_agent_id' => $ev['signer_first_agent_id'],
            'signer_second_agent_name' => $ev['signer_second_agent_name'],
            'signer_second_agent_id' => $ev['signer_second_agent_id'],
            'signer_agent_type_name' => $ev['signer_agent_type_name'],
        ];
    }
    //生成excel文件
    $tmpFileName = '/' . date("Y-m-d") . '撞单数据统计.xlsx';
    $tmpFileSavePath = $attachmentFilePath . $tmpFileName;
    try {
        Spreadsheet::createXml($tmpFileSavePath, $excelTitle, $excelData);
        $tmpFileNameList[] = $tmpFileSavePath;
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        SimpleLogger::error("create hit order excel file error", ['error_msg' => $e->getMessage()]);
    }
} else {
    $historyHitCount = $historyReferralAndAgentHitCount = $historyAgentAndAgentHitCount =
    $lastWeekHitCount = $lastWeekReferralAndAgentHitCount = $lastWeekAgentAndAgentHitCount = 0;
}

$historyCount = $historyYearCardCount = $lastWeekCount = $lastWeekYearCardCount = 0;
//历史代理订单总数
$historyCount = AgentAwardBillExtModel::getCount([]);
//历史代理年卡订单总数
$historyYearCardCount = AgentAwardBillExtModel::getCount(['package_type' => AgentAwardBillExtModel::PACKAGE_TYPE_YEAR]);
//上周代理订单总数
$lastWeekCount = AgentAwardBillExtModel::getCount(["create_time[<>]" => [$startTime, $endTime]]);
//上周代理年卡订单总数
$lastWeekYearCardCount = AgentAwardBillExtModel::getCount([
    'package_type' => AgentAwardBillExtModel::PACKAGE_TYPE_YEAR,
    "create_time[<>]" => [$startTime, $endTime]
]);

//组合数据
$emailsConfig = DictConstants::getTypesMap([DictConstants::AGENT_HIT_EMAILS['type']])[DictConstants::AGENT_HIT_EMAILS['type']];
$title = date("Y-m-d") . "撞单数据统计汇总";
$fontSize = '6';
$fontColor = '#dc143c';
$content = "代理系统目前订单总数:<br>
            上周代理订单总数统计:<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $lastWeekCount . "</font>单，其中体验卡<font color='" . $fontColor . "' size='" . $fontSize . "'>" . ($lastWeekCount - $lastWeekYearCardCount) . "</font>单，年卡<font color='" . $fontColor . "' size='" . $fontSize . "''>" . $lastWeekYearCardCount . "</font>单。<br>
            历史代理订单总数统计:<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyCount . "</font>单，其中体验卡<font color='" . $fontColor . "' size='" . $fontSize . "'>" . ($historyCount - $historyYearCardCount) . "</font>单，年卡<font color='" . $fontColor . "' size='" . $fontSize . "''>" . $historyYearCardCount . "</font>单。<br><br>";

$content .= "代理系统撞单统计如下:<br>
            上周撞单总数统计:撞单总数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $lastWeekHitCount . "</font>，撞单率为<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($lastWeekHitCount, $lastWeekCount, 4) * 100 . "</font>%。<br>
            其中转介绍与代理撞单订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $lastWeekReferralAndAgentHitCount . "</font>，占比<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($lastWeekReferralAndAgentHitCount, $lastWeekHitCount, 4) * 100 . "</font>%；代理与代理撞单的订单数<font color='" . $fontColor . "' size='" . $fontSize . "''>" . $lastWeekAgentAndAgentHitCount . "</font>，占比<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($lastWeekAgentAndAgentHitCount, $lastWeekHitCount, 4) * 100 . "</font>%。<br>
            历史撞单总数统计:撞单总数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyHitCount . "</font>，撞单率为<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($historyHitCount, $historyCount, 4) * 100  . "</font>%。<br>
            其中转介绍与代理撞单订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyReferralAndAgentHitCount . "</font>，占比<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($historyReferralAndAgentHitCount, $historyHitCount, 4) * 100 . "</font>%；代理与代理撞单的订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyAgentAndAgentHitCount . "</font>，占比<font color='" . $fontColor . "' size='" . $fontSize . "'>" . bcdiv($historyAgentAndAgentHitCount, $historyHitCount, 4) * 100 . "</font>%。<br><br>
            <font color='" . $fontColor . "'>注：用户同时存在学生转介绍关系、绑定中的代理关系、又通过其他代理购买，该订单属于三方撞单（此订单在转介绍与代理撞单、代理与代理撞单中均会存在）。</font>";
//发送邮件
PhpMail::sendEmail(array_shift($emailsConfig)['value'], $title, $content, $tmpFileSavePath, array_column($emailsConfig, 'value'));
//记录执行日志
$endMemory = memory_get_usage();
$execEndTime = time();
//删除临时文件
unlink($tmpFileSavePath);
SimpleLogger::info('agent hit bill end', ['memory' => $startMemory - $endMemory, 'exec_time' => $execsStartTime - $execEndTime]);

