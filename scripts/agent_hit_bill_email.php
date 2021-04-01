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
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Libs\Spreadsheet;
use App\Libs\Util;
use App\Models\AgentAwardBillExtModel;
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
list($startTime, $endTime) = Util::getDateWeekStartEndTime(strtotime("-2 days"));

//历史撞单总数
$historyHitCount = AgentAwardBillExtModel::getCount(['is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_YES]);
if (!empty($historyHitCount)) {
    //历史转介绍与代理撞单订单数
    $historyReferralAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            "AND" => [
                'is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_YES,
                "OR" => [
                    "OR #the first condition" => [
                        'student_referral_id[!]' => 0,
                        'own_agent_id[!]' => 0,
                    ],
                    "OR #the second condition" => [
                        'student_referral_id[!]' => 0,
                        'signer_agent_id[!]' => 0,
                    ]
                ]
            ]
        ]);
    //历史代理与代理撞单的订单数
    $historyAgentAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            "AND" => [
                'own_agent_id[!=]signer_agent_id',
                'is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_YES,
            ]
        ]);


    //上周撞单总数
    $lastWeekHitCount = AgentAwardBillExtModel::getCount(
        [
            "create_time[<>]" => [$startTime, $endTime],
            'is_first_order' => AgentAwardBillExtModel::IS_FIRST_ORDER_YES,
        ]);
    //上周转介绍与代理撞单订单数
    $lastWeekReferralAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            "AND" => [
                "create_time[<>]" => [$startTime, $endTime],
                "OR" => [
                    "OR #the first condition" => [
                        'student_referral_id[!]' => 0,
                        'own_agent_id[!]' => 0,
                    ],
                    "OR #the second condition" => [
                        'student_referral_id[!]' => 0,
                        'signer_agent_id[!]' => 0,
                    ]
                ],
                'is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_YES,
            ]
        ]);
    //上周代理与代理撞单的订单数
    $lastWeekAgentAndAgentHitCount = AgentAwardBillExtModel::getCount(
        [
            "AND" => [
                "create_time[<>]" => [$startTime, $endTime],
                'own_agent_id[!=]signer_agent_id',
                'is_hit_order' => AgentAwardBillExtModel::IS_HIT_ORDER_YES,
            ]
        ]);
    if (empty($historyHitCount)) {
        return true;
    }
    //分批次获取一次500条
    $pageGetCount = 500;
    $params = [
        'only_read_self' => false,
        'is_hit_order' => AgentAwardBillExtModel::IS_FIRST_ORDER_YES,
        'page' => 1,
        'count' => $pageGetCount,
    ];
    $hitOrderList = AgentService::recommendBillsList($params, 0);
    $totalOrderData = $hitOrderList['list'];

    for ($i = 2; $i <= ceil($hitOrderList['count'] / $pageGetCount); $i++) {
        $params['page'] = $i;
        array_merge($totalOrderData, AgentService::recommendBillsList($params, 0)['list']);
    }
    //撞单的订单明细表字段
    foreach ($totalOrderData as $ek => $ev) {
        $excelData[date('Y-m', $ev['buy_time'])][] = [
            'parent_bill_id' => $ev['parent_bill_id'],
            'student_name' => $ev['student_name'],
            'student_uuid' => $ev['student_uuid'],
            'student_mobile' => $ev['student_mobile'],
            'package_name' => $ev['package_name'],
            'buy_time' => date("Y-m-d H:i:s", $ev['buy_time']),
            'code_status_name' => $ev['code_status_name'],
            'first_agent_name' => $ev['first_agent_name'],
            'first_agent_id' => $ev['first_agent_id'],
            'student_referral_id' => $ev['student_referral_id'],
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
    foreach ($excelData as $edk => $edv) {
        try {
            Spreadsheet::createXml($tmpFileSavePath, $excelTitle, $edv, 0, $edk);
            $tmpFileNameList[] = $tmpFileSavePath;
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            SimpleLogger::error("create hit order excel file error", ['error_msg' => $e->getMessage()]);
        }
    }
} else {
    $historyHitCount = $historyReferralAndAgentHitCount = $historyAgentAndAgentHitCount =
    $lastWeekHitCount = $lastWeekReferralAndAgentHitCount = $lastWeekAgentAndAgentHitCount = 0;
}
//组合数据
$emailsConfig = DictConstants::getTypesMap([DictConstants::AGENT_HIT_EMAILS['type']])[DictConstants::AGENT_HIT_EMAILS['type']];
$title = date("Y-m-d") . "撞单数据统计汇总";
$fontSize = '6';
$fontColor = '#dc143c';
$content = "代理系统撞单统计如下:<br>
            上周撞单总数统计:撞单总数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $lastWeekHitCount . "</font>,转介绍与代理撞单订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $lastWeekReferralAndAgentHitCount . "</font>,代理与代理撞单的订单数<font color='" . $fontColor . "' size='" . $fontSize . "''>" . $lastWeekAgentAndAgentHitCount . "</font>。<br>
            历史撞单总数统计:撞单总数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyHitCount . "</font>,转介绍与代理撞单订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyReferralAndAgentHitCount . "</font>,代理与代理撞单的订单数<font color='" . $fontColor . "' size='" . $fontSize . "'>" . $historyAgentAndAgentHitCount . "</font>。<br>
            <font color='" . $fontColor . "'>注：用户同时存在学生转介绍关系、绑定中的代理关系、又通过其他代理购买，该订单属于三方撞单（此订单在转介绍与代理撞单、代理与代理撞单中均会存在）。</font>";
//发送邮件
PhpMail::sendEmail(array_shift($emailsConfig)['value'], $title, $content, $tmpFileSavePath, array_column($emailsConfig, 'value'));
//记录执行日志
$endMemory = memory_get_usage();
$execEndTime = time();
//删除临时文件
unlink($tmpFileSavePath);
SimpleLogger::info('agent hit bill end', ['memory' => $startMemory - $endMemory, 'exec_time' => $execsStartTime - $execEndTime]);

