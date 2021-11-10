<?php
/**
 * 用户推荐奖励
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\ReferralRulesModel;
use App\Models\ReferralUserAwardModel;
use App\Models\StudentReferralStudentDetailModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\WeChatConfigModel;
use App\Services\Queue\QueueService;
use Exception;

trait TraitUserRefereeService
{

    /**
     * 生成学生转介绍学生推荐奖励方法统一入口
     * 注意调用此方法前需要判断是否有转介绍关系
     * @param $packageType
     * @param $refereeInfo
     * @param $parentBillId
     * @param $studentInfo
     * @return bool
     * @throws RunTimeException
     */
    public static function createStudentRefereeAward($packageType, $refereeInfo, $parentBillId, $studentInfo): bool
    {
        // 没有推荐人
        if (empty($refereeInfo)) {
            SimpleLogger::info('not referee user', [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
            throw new RunTimeException(['not_referee_user'], [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
        }
        // 获取订单的时长
        $billInfo = DssGiftCodeModel::getRecord(['parent_bill_id' => $parentBillId], ['id', 'valid_num', 'valid_units', 'parent_bill_id']);
        if (empty($billInfo)) {
            SimpleLogger::info("parent_bill_id_is_not_found", [$parentBillId, $packageType, $refereeInfo, $billInfo]);
            throw new RunTimeException(['parent_bill_id_is_not_found'], [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
        }

        // 获取邀请人身份
        $refereeIdentity = self::getReferralStudentIdentity($refereeInfo);
        // 获取奖励规则
        $ruleInfo = ReferralRulesModel::getCurrentRunRuleInfoByInviteStudentIdentity($refereeIdentity, ReferralRulesModel::TYPE_AI_STUDENT_REFEREE, [$packageType]);
        if (empty($ruleInfo)) {
            SimpleLogger::info("rules_empty", [$ruleInfo, $refereeIdentity, ReferralRulesModel::TYPE_AI_STUDENT_REFEREE, $parentBillId, $packageType, $refereeInfo, $billInfo]);
            return false;
        }
        // 生成奖励
        self::createStudentAward($packageType, $ruleInfo, $billInfo, $refereeInfo, $studentInfo);
        return true;
    }

    /**
     * 获取邀请人(学生) 身份
     * @param $refereeInfo
     * @return int
     */
    public static function getReferralStudentIdentity($refereeInfo): int
    {
        $time = time();
        $studentStatus = -1;
        switch ($refereeInfo['has_review_course']) {
            case DssStudentModel::REVIEW_COURSE_49:
                // 体验卡未过期
                $studentStatus = Constants::REFERRAL_INVITER_STATUS_TRAIL;
                // 体验卡过期未付费正式课
                if (strtotime($refereeInfo['sub_end_date']) < $time) {
                    $studentStatus = Constants::REFERRAL_INVITER_STATUS_TRAIL_EXPIRE;
                }
                break;
            case DssStudentModel::REVIEW_COURSE_1980:
                // 年卡未过期 - 需要有有效时长
                if (UserService::judgeUserValidPay($refereeInfo['id'])) {
                    $studentStatus = Constants::REFERRAL_INVITER_STATUS_NORMAL;
                } elseif (strtotime($refereeInfo['sub_end_date']) + Util::TIMESTAMP_ONEDAY < $time) {
                    // 不是有效付费用户 - 年卡过期
                    $studentStatus = Constants::REFERRAL_INVITER_STATUS_NORMAL_EXPIRE;
                }
                break;
            default:
                // 仅注册
                $studentStatus = Constants::REFERRAL_INVITER_STATUS_REGISTER;
                break;
        }
        SimpleLogger::info("getReferralStudentIdentity", [$refereeInfo, $studentStatus]);
        return $studentStatus;
    }

    /**
     * 2021.10.08 转介绍奖励规则 - 请勿直接调用，需要通过 self::createStudentRefereeAward 调用
     * 生成奖励
     * 规则以后台配置为准
     * @param $packageType
     * @param $ruleInfo
     * @param $billInfo
     * @param $refereeInfo
     * @param $studentInfo
     * @return bool
     * @throws RunTimeException
     */
    private static function createStudentAward($packageType, $ruleInfo, $billInfo, $refereeInfo, $studentInfo): bool
    {
        SimpleLogger::info("createStudentAward_start", [$packageType, $ruleInfo, $billInfo, $refereeInfo, $studentInfo]);
        $awardList = [];
        if (empty($ruleInfo) || empty($ruleInfo['rule_list'])) {
            throw new RunTimeException(["rule_empty"], [$packageType, $ruleInfo]);
        }
        if (!in_array($packageType, [DssPackageExtModel::PACKAGE_TYPE_NORMAL, DssPackageExtModel::PACKAGE_TYPE_TRIAL])) {
            throw new RunTimeException(["package_type_error"], [$packageType, $ruleInfo]);
        }
        // 循环处理奖励规则
        foreach ($ruleInfo['rule_list'] as $_rule) {
            // 检查是否满足生成奖励条件
            if (!self::checkAwardCondition($_rule, $packageType, $billInfo)) {
                continue;
            }
            // 组装奖励数据
            $awardList = self::composeAwardData($_rule, $packageType, $refereeInfo, $studentInfo, $billInfo);
        }
        unset($_rule, $_tmp);

        // 奖励不为空，发放奖励
        if (!empty($awardList)) {
            // 获取转介绍关系
            $studentInviteStage = $packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL ? StudentReferralStudentStatisticsModel::STAGE_TRIAL : StudentReferralStudentStatisticsModel::STAGE_FORMAL;
            $studentStageInfo   = StudentReferralStudentDetailModel::getRecord(['student_id' => $studentInfo['id'], 'stage' => $studentInviteStage], ['id']);
            // 生成批次id
            $batchId = RealUserAwardMagicStoneService::getBatchId();
            // 生成待发放奖励
            foreach ($awardList as $_award) {
                // 补全数据
                $_award['batch_id']         = $batchId;
                $_award['invite_detail_id'] = $studentStageInfo['id'];

                // 如果是金叶子还需要通知erp 产生待发放记录
                switch ($_award['award_type']) {
                    case Constants::AWARD_TYPE_GOLD_LEAF:
                        $goldLeafIds                               = self::sendAwardGoldLeaf($_award);
                        $_award['other_data']['erp_gold_leaf_ids'] = implode(',', $goldLeafIds);
                        break;
                }

                // 增加一条发放记录
                $_awardId = ReferralUserAwardModel::addOne($_award);
                if (!$_awardId) {
                    SimpleLogger::info("save_award_error", [$_award, $_awardId]);
                    continue;
                }
                SimpleLogger::info("save_award_record_success", [$_award, $_awardId]);
            }
            unset($_award);
        } else {
            SimpleLogger::info('no_patch_award', [$packageType, $ruleInfo]);
        }
        return true;
    }

    /**
     * 组装发放奖励需要的数据
     * @param $ruleAwardInfo
     * @param $packageType
     * @param $refereeInfo
     * @param $studentInfo
     * @param $billInfo
     * @return array
     */
    private static function composeAwardData($ruleAwardInfo, $packageType, $refereeInfo, $studentInfo, $billInfo): array
    {
        SimpleLogger::info("composeAwardData_start", [$ruleAwardInfo, $packageType, $refereeInfo, $studentInfo, $billInfo]);
        // 解析限制条件
        $restrictions = json_decode($ruleAwardInfo['restrictions'], true);
        // 解析奖励明细
        $awardDetails = json_decode($ruleAwardInfo['reward_details'], true);

        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            $awardNode = Constants::AWARD_NODE_NORMAL_AWARD;
            $days      = $restrictions['refund_limit_min_days'];
        } else {
            $awardNode = Constants::AWARD_NODE_TRAIL_AWARD;
            $days      = $restrictions['days'];
        }

        // 计算发放奖励延时时间
        $awardDelay = $days * Util::TIMESTAMP_ONEDAY;
        // 练琴时间单位秒
        $playTimes = $restrictions['play_times'] ?? 0;

        // 组合奖励数据
        $returnAwardData = [];
        foreach ($awardDetails as $_invite => $_awardList) {
            if ($_invite == 'invited') {
                $_userId   = $refereeInfo['id'];
                $_userUuid = $refereeInfo['uuid'];
                $_awardTo  = Constants::STUDENT_ID_INVITER;
            } else {
                $_userId   = $studentInfo['id'];
                $_userUuid = $studentInfo['uuid'];
                $_awardTo  = Constants::STUDENT_ID_INVITEE;
            }
            foreach ($_awardList as $_award) {
                // 检查当前奖励规则是否有效的 - 无效的不发放奖励
                if (self::checkAwardRuleIsInvalid($_award)) {
                    SimpleLogger::info("composeAwardData_award_amount_is_zero", [$ruleAwardInfo, $packageType, $billInfo]);
                    continue;
                }
                $taskId            = self::getAwardTaskId($_award['award_type'], $packageType);
                $returnAwardData[] = [
                    'award_type'       => $_award['award_type'],        // 奖励类型
                    'award_node'       => $awardNode,                   // 奖励类型标记
                    'event_task_id'    => $taskId,                      // 任务id
                    'award_rule_id'    => $ruleAwardInfo['id'],         // 规则id
                    'award_time'       => $awardDelay + time(),         // 发放时间（单位s）
                    'award_delay'      => $awardDelay,                  // 发放时间（单位s）
                    'award_amount'     => $_award['award_amount'],      // 奖励数量 （金叶子、时长单位秒）
                    'award_to'         => $_awardTo,                    // 奖励人 (invited:邀请人， invitee:受邀人)
                    'invited_id'       => $refereeInfo['id'],           // 邀请人id
                    'invited_uuid'     => $refereeInfo['uuid'],         // 邀请人uuid
                    'invitee_id'       => $studentInfo['id'],           // 受邀人id
                    'invitee_uuid'     => $studentInfo['uuid'],         // 受邀人uuid
                    'user_id'          => $_userId,                     // 得奖人id
                    'uuid'             => $_userUuid,                   // 得奖人uuid
                    'finish_task_uuid' => $studentInfo['uuid'],         // 完成任务的人
                    'bill_id'          => $billInfo['parent_bill_id'],  // 订单号
                    'package_type'     => $packageType,                 // 产品包类型
                    'award_condition'  => [                             // 奖励条件
                        'play_times' => $playTimes, // 练琴时间单位秒
                    ],
                ];
            }
            unset($_award);
        }
        unset($_awardList, $_invite);

        return $returnAwardData;
    }

    /**
     * 检查用户是否满足生成奖励条件
     * @param $ruleAwardInfo
     * @param $packageType
     * @param $billInfo
     * @return bool
     */
    private static function checkAwardCondition($ruleAwardInfo, $packageType, $billInfo): bool
    {
        // 检查产品包类型
        if ($ruleAwardInfo['type'] != $packageType) {
            return false;
        }
        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            // 年卡奖励条件
            if (!self::checkNormalAwardCondition($ruleAwardInfo, $billInfo)) {
                return false;
            }
        } elseif ($packageType == DssPackageExtModel::PACKAGE_TYPE_TRIAL) {
            // 体验卡奖励条件， 首次购买 - 这个在前面已经做了检查，这里不在重复检查
            // 检查限制条件
            SimpleLogger::info("checkAwardCondition_package_type_trail", [$ruleAwardInfo, $packageType, $billInfo]);
        } else {
            SimpleLogger::info("checkAwardCondition_package_type_error", [$ruleAwardInfo, $packageType, $billInfo]);
            return false;
        }

        return true;
    }

    /**
     * 检查当前奖励规则是否有效的 - 无效的不发放奖励
     * 注意：这里的无效指的是即使产生奖励用户也没有收到实际奖励，例如奖励额度是0
     * 注意：这里的无效和奖励开关是不同的
     * @param $awardRule
     * @return bool
     */
    private static function checkAwardRuleIsInvalid($awardRule): bool
    {
        // 检查奖励额度是否有效 - 小于0不发放
        if ($awardRule['award_amount'] <= 0) {
            return true;
        }
        return false;
    }

    /**
     * 检查年卡的奖励条件是否符合
     * @param $ruleAwardInfo
     * @param $billInfo
     * @return bool
     */
    private static function checkNormalAwardCondition($ruleAwardInfo, $billInfo): bool
    {
        $awardCondition = json_decode($ruleAwardInfo['reward_condition'], true);
        // 产品包产品时长在指定时间范围内
        if (!($awardCondition['package_duration_min'] <= $billInfo['valid_num'] && $billInfo['valid_num'] <= $awardCondition['package_duration_max'])) {
            return false;
        }
        return true;
    }

    /**
     * 请求erp - 生成待发放奖励 - 金叶子
     * @param $awardInfo
     * @return array
     */
    public static function sendAwardGoldLeaf($awardInfo): array
    {
        $awardStatus = $awardInfo['award_status'] ?? ReferralUserAwardModel::STATUS_WAITING;
        $taskResult = (new Erp())->addEventTaskAward($awardInfo['finish_task_uuid'], $awardInfo['event_task_id'], $awardStatus, $awardInfo['task_award_id'], $awardInfo['invited_uuid'], [
            'bill_id'          => $awardInfo['bill_id'],
            'package_type'     => $awardInfo['package_type'],
            'invite_detail_id' => $awardInfo['invite_detail_id'],
            'activity_id'      => 0,
            'delay'            => $awardInfo['award_delay'],
            'amount'           => $awardInfo['award_amount'],
            'award_to'         => $awardInfo['award_to'],
            'reason'           => $awardInfo['review_reason'],
        ]);
        SimpleLogger::info("UserRefereeService::dssCompleteEventTask", [
            'params'   => [
                $awardInfo
            ],
            'response' => $taskResult,
        ]);
        $pointsIds = $taskResult['data']['points_award_ids'] ?? [];
        return is_array($pointsIds) ? $pointsIds : [];
    }

    /**
     * 获取奖励类型和产品包对应的任务id
     * @param $awardType
     * @param $packageType
     * @return mixed
     */
    public static function getAwardTaskId($awardType, $packageType)
    {
        SimpleLogger::info("getAwardTaskId_start", [$awardType, $packageType]);
        // 获取奖励对应的task_id
        list($taskIdTrailAwardGoldLeft, $taskIdTrailAwardTime, $taskIdNormalAwardGoldLeft, $taskIdNormalAwardTime) = DictConstants::get(DictConstants::REFERRAL_CONFIG, [
            'trial_award_gold_left_task_id',
            'trial_award_time_task_id',
            'normal_award_gold_left_task_id',
            'normal_award_time_task_id',
        ]);
        if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
            $taskId = $awardType == Constants::AWARD_TYPE_TIME ? $taskIdNormalAwardTime : $taskIdNormalAwardGoldLeft;
        } else {
            $taskId = $awardType == Constants::AWARD_TYPE_TIME ? $taskIdTrailAwardTime : $taskIdTrailAwardGoldLeft;
        }
        return $taskId;
    }

    /**
     * 获取订单退费信息
     * @param $billIds
     * @return array
     */
    public static function getBillIdsRefundTime($billIds): array
    {
        $refundTimeMap = [];
        if (empty($billIds)) {
            return $refundTimeMap;
        }
        // 请求erp 获取数据
        $erp         = new Erp();
        $arrBillId   = array_values(array_unique($billIds));
        $batchBillId = array_chunk($arrBillId, 100);
        foreach ($batchBillId as $billIds) {
            $refundInfo  = $erp->getRefundTime($billIds);
            $refundTimes = $refundInfo['data'] ?? [];
            foreach ($refundTimes as $billId => $refundTime) {
                $arrRefundTime          = array_column($refundTime, 'refund_time');
                $refundTimeMap[$billId] = empty($arrRefundTime) ? 0 : min($arrRefundTime);
            }
        }
        return $refundTimeMap;
    }

    /**
     * 给用户推送消息
     * @param $pushMsgTaskId
     * @param $awardTo
     * @param $studentUuid
     * @param $studentId
     * @param array $msgData
     * @return bool
     */
    public static function pushUserMsg($pushMsgTaskId, $awardTo, $studentUuid, $studentId, array $msgData = []): bool
    {
        try {
            $wechatConfigInfo = WeChatConfigModel::getRecord(['event_task_id' => $pushMsgTaskId, 'to' => $awardTo]);
            if (empty($wechatConfigInfo)) {
                SimpleLogger::info('not_found_wechat_config', [$pushMsgTaskId, $awardTo, $studentUuid, $studentId, $msgData]);
                return false;
            }
            QueueService::sendGoldLeafWxMessage(array_merge([
                'student_id'       => $studentId,
                'uuid'             => $studentUuid,
                'wechat_config_id' => $wechatConfigInfo['id'],
            ], $msgData));
        } catch (Exception $e) {
            SimpleLogger::info("send_msg_error_exception", []);
            return false;
        }
        return true;
    }
}
