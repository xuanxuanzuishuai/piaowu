<?php
/**
 * 定时发放用户转介绍奖励
 * 数据来源表 op referral_user_award
 *
 * 后续应该统一发放脚本
 */

namespace App;

date_default_timezone_set('PRC');
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_ROOT', PROJECT_ROOT . '/app');
define('TEMP_ROOT', APP_ROOT . '/Templates');
define('LANG_ROOT', PROJECT_ROOT . '/lang');

require_once PROJECT_ROOT . '/vendor/autoload.php';

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\CHModel\AprViewStudentModel;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpUserEventTaskAwardGoldLeafModel;
use App\Models\ReferralUserAwardModel;
use App\Services\Queue\QueueService;
use App\Services\UserRefereeService;
use Dotenv\Dotenv;

$dotenv = new Dotenv(PROJECT_ROOT, '.env');
$dotenv->load();
$dotenv->overload();

class ScriptSendReferralUserAward
{

    /**
     * 运行入口
     */
    public function run()
    {
        // 发放购买正式时长(年卡)奖励
        self::sendAwardNodeByNormalAward();
        // 发放购买体验卡奖励
        self::sendAwardNodeByTrailAward();
    }

    /**
     * 发放购买正式时长(年卡)奖励 - 奖励节点是normal_award
     */
    public static function sendAwardNodeByNormalAward()
    {
        $sendAwardTime = strtotime(date("Y-m-d 00:00:00"));
        // 查询发放日期是上一天的所有未发放的年卡奖励
        $where     = [
            'award_time[<]' => $sendAwardTime,
            'award_status'  => [ReferralUserAwardModel::STATUS_WAITING, ReferralUserAwardModel::STATUS_GIVE_FAIL],
            'award_node'    => Constants::AWARD_NODE_NORMAL_AWARD
        ];
        $awardList = ReferralUserAwardModel::getRecords($where);
        if (empty($awardList)) {
            SimpleLogger::info("scriptSendReferralUserAward", ['info' => "is_empty_points_list", 'where' => $where]);
            return 'success';
        }

        // 获取退费信息
        foreach ($awardList as $_award) {
            if ($_award['award_status'] != ReferralUserAwardModel::STATUS_WAITING) {
                continue;
            }
            $billIds[] = $_award['bill_id'];
        }
        unset($_award);
        $refundTimeMap = UserRefereeService::getBillIdsRefundTime($billIds ?? []);

        // 发放奖励
        foreach ($awardList as $_award) {
            $otherData = json_decode($_award['other_data'], true);
            $erpTaskAwardId = $otherData[ReferralUserAwardModel::OTHER_DATA['erp_gold_leaf_ids']] ?? '';
            $pushMsgTaskId = UserRefereeService::getAwardTaskId($_award['award_type'], $_award['package_type']);
            $sendAwardData = [
                'is_refund' => false,   // 是否退费
                'push_amount' => '',     // 奖励额度 奖励是时长时：是购买产品包的天数；奖励是金叶子时：金叶子数量
                'url' => '',
            ];
            // 如果是待发放需要校验是否退费
            if ($_award['award_status'] == ReferralUserAwardModel::STATUS_WAITING) {
                //订单退款时间
                $refundTime = $refundTimeMap[$_award['bill_id']] ?? 0;
                // 如果是退费 - 标记作废
                if ($refundTime > 0 && $refundTime <= $_award['award_time']) {
                    SimpleLogger::info("script::auto_send_rt_award_points", [$refundTime, $_award]);
                    $sendAwardData['is_refund'] = true;
                }
            }
            // 开始处理奖励
            if ($_award['award_type'] == Constants::AWARD_TYPE_TIME && $sendAwardData['is_refund']) {
                /** 奖励时长 && 已退费 */
                ReferralUserAwardModel::disabledAwardByRefund($_award['id']);
                continue;
            } elseif ($_award['award_type'] == Constants::AWARD_TYPE_TIME && !$sendAwardData['is_refund']) {
                /** 奖励时长 && 未退费 */
                ReferralUserAwardModel::successSendAward($_award['id']);
                // 赠送自动激活码
                QueueService::giftDuration($_award['uuid'], DssGiftCodeModel::APPLY_TYPE_AUTO, $_award['award_amount'], DssGiftCodeModel::BUYER_TYPE_AI_REFERRAL);
                $sendAwardData['push_amount'] = $_award['award_amount'];
                $sendAwardData['url'] = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'award_time_wx_msg_url');
            } elseif ($_award['award_type'] == Constants::AWARD_TYPE_GOLD_LEAF && $sendAwardData['is_refund']) {
                /** 奖励金叶子 && 已退费 */
                ReferralUserAwardModel::disabledAwardByRefund($_award['id']);
                // 更新erp库对应的奖励记录为已作废
                UserRefereeService::sendAwardGoldLeaf(array_merge($_award, [
                    'task_award_id' => $erpTaskAwardId,
                    'award_status'  => ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED,
                    'event_task_id' => $pushMsgTaskId,
                ]));
                continue;
            } elseif ($_award['award_type'] == Constants::AWARD_TYPE_GOLD_LEAF && !$sendAwardData['is_refund']) {
                /** 奖励金叶子 && 未退费 */
                ReferralUserAwardModel::successSendAward($_award['id']);
                // 更新erp库对应的奖励记录为发放成功 - 并且发放金叶子
                UserRefereeService::sendAwardGoldLeaf(array_merge($_award, [
                    'task_award_id' => $erpTaskAwardId,
                    'award_status'  => ErpUserEventTaskAwardGoldLeafModel::STATUS_REVIEWING, // 等于2代表的是本次会把奖励直接发放给用户
                    'event_task_id' => $pushMsgTaskId,
                ]));
                $sendAwardData['push_amount'] = $_award['award_amount'];
            } else {
                /** 未知条件不处理 */
                SimpleLogger::info('scriptSendReferralUserAward_award_type_error', [$_award['award_type']]);
                continue;
            }

            // 记录日志
            SimpleLogger::info('scriptSendReferralUserAward_award_send_success', [$_award]);

            // 推送消息
            UserRefereeService::pushUserMsg($pushMsgTaskId, $_award['award_to'], $_award['uuid'], $_award['user_id'], [
                'amount' => $sendAwardData['push_amount'],
                'url' => $sendAwardData['url'],
            ]);
        }
        unset($_award);
        return true;
    }

    /**
     * 发放购买体验卡奖励 - 奖励节点是trail_award
     */
    public static function sendAwardNodeByTrailAward()
    {
        $time       = time();
        $createTime = strtotime(date("Y-m-d 00:00:00"));
        // 查询今天之前的所有待发放和发放失败的数据（体验卡奖励）
        $where     = [
            'create_time[<]' => $createTime,
            'award_status'   => [ReferralUserAwardModel::STATUS_WAITING, ReferralUserAwardModel::STATUS_GIVE_FAIL],
            'award_node'     => Constants::AWARD_NODE_TRAIL_AWARD
        ];
        $awardList = ReferralUserAwardModel::getRecords($where);
        if (empty($awardList)) {
            SimpleLogger::info("sendAwardNodeByTrailAward", ['info' => "is_empty_points_list", 'where' => $where]);
            return 'success';
        }

        foreach ($awardList as $_award) {
            $otherData = json_decode($_award['other_data'], true);
            $erpTaskAwardId = $otherData[ReferralUserAwardModel::OTHER_DATA['erp_gold_leaf_ids']] ?? '';
            $pushMsgTaskId = UserRefereeService::getAwardTaskId($_award['award_type'], $_award['package_type']);
            // 奖励条件
            $awardCondition = json_decode($_award['award_condition'], true);
            // 计算时间  练琴时间范围按小时计算
            $playStartTime = strtotime(date('Y-m-d H:00:00', $_award['create_time']));
            $playEndTime   = strtotime(date('Y-m-d H:00:00', $_award['award_time'])) + Util::TIMESTAMP_1H - 1;
            // 获取受邀人信息
            $studentInfo = DssStudentModel::getRecord(['uuid' => $_award['finish_task_uuid']], ['id']);
            // 计算是否有练琴记录 - 获取指定时间段内最早练琴时间
            $earliestList = AprViewStudentModel::getStudentBetweenTimePlayRecord((int)$studentInfo['id'], (int)$playStartTime, (int)$playEndTime);
            $earliestTime = array_sum(array_column($earliestList, 'sum_duration'));
            SimpleLogger::info("script::auto_send_buy_trial_award_points", ['info' => 'aiPlay', 'data' => $earliestList, 'award_info' => $_award, 'play_time' => [$playStartTime, $playEndTime]]);
            if ($awardCondition[ReferralUserAwardModel::AWARD_CONDITION['play_times']] <= 0) {
                /** 满足发放 - 没有设置必须要参与练琴 */
                ReferralUserAwardModel::successSendAward($_award['id']);
                switch ($_award['award_type']) {
                    case Constants::AWARD_TYPE_GOLD_LEAF:   // 发放金叶子
                        // 更新erp库对应的奖励记录为发放成功 - 并且发放金叶子
                        UserRefereeService::sendAwardGoldLeaf(array_merge($_award, [
                            'task_award_id' => $erpTaskAwardId,
                            'award_status'  => ErpUserEventTaskAwardGoldLeafModel::STATUS_REVIEWING, // 等于2代表的是本次会把奖励直接发放给用户
                            'event_task_id' => $pushMsgTaskId,
                        ]));
                        $sendPushMsg = true;
                        break;
                    case Constants::AWARD_TYPE_TIME:    // 奖励时长
                        // 赠送自动激活码
                        QueueService::giftDuration($_award['uuid'], DssGiftCodeModel::APPLY_TYPE_AUTO, $_award['award_amount'], DssGiftCodeModel::BUYER_TYPE_AI_REFERRAL);
                        $url = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'award_time_wx_msg_url');
                        $sendPushMsg = true;
                        break;
                    default:
                        SimpleLogger::info("sendAwardNodeByTrailAward_not_award_type", [$_award, $playEndTime, $earliestList]);
                        break;
                }
                // 推送消息
                if (isset($sendPushMsg) && $sendPushMsg == true) {
                    UserRefereeService::pushUserMsg($pushMsgTaskId, $_award['award_to'], $_award['uuid'], $_award['user_id'], [
                        'amount' => $_award['award_amount'],
                        'url' => $url ?? '',
                    ]);
                }
            } elseif ((empty($earliestTime) && $playEndTime <= $time) // 指定时间内没有练琴
                ||
                ($earliestTime > 0 && $earliestTime < $awardCondition[ReferralUserAwardModel::AWARD_CONDITION['play_times']] && $playEndTime <= $time)   // 指定时间内练琴时长不足
            ) {
                /** 作废 - 指定时间段内没有练琴，并且最后练琴时间已经超过当前时间 */
                SimpleLogger::info("play_time_not_found", [$playEndTime, $earliestTime, $time]);
                ReferralUserAwardModel::disabledAwardByNoPlay($_award['id']);
                switch ($_award['award_type']) {
                    case Constants::AWARD_TYPE_GOLD_LEAF:   // 作废金叶子
                        // 更新erp库对应的奖励记录为作废
                        UserRefereeService::sendAwardGoldLeaf(array_merge($_award, [
                            'task_award_id' => $erpTaskAwardId,
                            'award_status'  => ErpUserEventTaskAwardGoldLeafModel::STATUS_DISABLED, // 等于2代表的是本次会把奖励直接发放给用户
                            'event_task_id' => $pushMsgTaskId,
                            'review_reason' => ReferralUserAwardModel::REASON_NO_PLAY,
                        ]));
                        $sendPushMsg = true;
                        break;
                    default:
                        SimpleLogger::info("sendAwardNodeByTrailAward_not_award_type", [$_award, $playEndTime, $earliestList]);
                        break;
                }
            } elseif (!empty($earliestTime) && $earliestTime >= $awardCondition[ReferralUserAwardModel::AWARD_CONDITION['play_times']]) {
                /** 满足发放 - 练琴时间大于等于获得奖励的最低时间 */
                ReferralUserAwardModel::successSendAward($_award['id']);
                switch ($_award['award_type']) {
                    case Constants::AWARD_TYPE_GOLD_LEAF:   // 发放金叶子
                        // 更新erp库对应的奖励记录为发放成功 - 并且发放金叶子
                        UserRefereeService::sendAwardGoldLeaf(array_merge($_award, [
                            'task_award_id' => $erpTaskAwardId,
                            'award_status'  => ErpUserEventTaskAwardGoldLeafModel::STATUS_REVIEWING, // 等于2代表的是本次会把奖励直接发放给用户
                            'event_task_id' => $pushMsgTaskId,
                        ]));
                        $sendPushMsg = true;
                        break;
                    case Constants::AWARD_TYPE_TIME:    // 奖励时长
                        // 赠送自动激活码
                        QueueService::giftDuration($_award['uuid'], DssGiftCodeModel::APPLY_TYPE_AUTO, $_award['award_amount'], DssGiftCodeModel::BUYER_TYPE_AI_REFERRAL);
                        $url = DictConstants::get(DictConstants::REFERRAL_CONFIG, 'award_time_wx_msg_url');
                        $sendPushMsg = true;
                        break;
                    default:
                        SimpleLogger::info("sendAwardNodeByTrailAward_not_award_type", [$_award, $playEndTime, $earliestList]);
                        break;
                }
                // 推送消息
                if (isset($sendPushMsg) && $sendPushMsg == true) {
                    UserRefereeService::pushUserMsg($pushMsgTaskId, $_award['award_to'], $_award['uuid'], $_award['user_id'], [
                        'amount' => $_award['award_amount'],
                        'url' => $url ?? '',
                    ]);
                }
            } else {
                SimpleLogger::info("sendAwardNodeByTrailAward_no_send_award", [$_award, $playEndTime, $earliestList]);
            }
        }
        unset($_award);
        return true;
    }
}

(new ScriptSendReferralUserAward())->run();
