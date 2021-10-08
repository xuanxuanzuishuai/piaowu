<?php


namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\ReferralRulesModel;
use App\Models\ReferralRulesRewardModel;

class ReferralRuleService
{
    /**
     * 添加转介绍活动奖励规则
     * @param $params
     * @param $operatorId
     * @return bool|int|mixed|null|string
     * @throws RunTimeException
     */
    public static function add($params, $operatorId)
    {
        //基础数据
        $baseData = self::ruleBaseDataCheck($params['name'], $params['rule_type'], $params['start_time'], $params['end_time'], $params['remark']);
        //付费体验卡奖励
        $trailRuleData = self::trailRuleDataCheck($params['trail_rule']);
        //付费正式时长奖励
        $normalRule = self::normalRuleDataCheck($params['normal_rule']);
        $addRes = ReferralRulesModel::addRule($baseData, $trailRuleData, $normalRule);
        if (empty($addRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        return $addRes;
    }

    /**
     * 基础数据检测
     * @param $name
     * @param $ruleType
     * @param $startTime
     * @param $endTime
     * @param $remark
     * @return array
     * @throws RunTimeException
     */
    private static function ruleBaseDataCheck($name, $ruleType, $startTime, $endTime, $remark)
    {
        $time = time();
        //时间关系检测
        if (($endTime <= $time) || ($endTime <= $startTime)) {
            throw new RunTimeException(['end_time_error']);
        }
        if (($startTime <= $time)) {
            throw new RunTimeException(['start_time_must_greater_than_current_time']);
        }
        return [
            'name' => trim($name),
            'type' => $ruleType,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'remark' => trim($remark),
            'create_time' => $time,
        ];
    }

    /**
     * 付费正式时长奖励规则检测
     * @param $ruleData
     * @return array
     * @throws RunTimeException
     */
    private static function normalRuleDataCheck($ruleData)
    {
        $normalRule = $inviteeStatus = [];
        $paramsInviteeStatus = array_column($ruleData, 'invitee_status');
        array_walk($paramsInviteeStatus, function ($rv) use (&$inviteeStatus) {
            $inviteeStatus = array_merge($rv, $inviteeStatus);
        });

        //受邀人奖励类型检测
        $invitedData = array_column($ruleData, 'invited');
        if (array_diff(array_column($invitedData, 'award_type'), [Constants::AWARD_TYPE_TIME, Constants::AWARD_TYPE_GOLD_LEAF])) {
            throw new RunTimeException(['award_type_is_error']);
        }

        if ((min(array_column($invitedData, 'award_amount')) < 0) || (max(array_column($invitedData, 'award_amount')) > 99999)) {
            throw new RunTimeException(['award_amount_is_greater_0']);
        }

        //邀请人奖励类型检测
        $inviteeData = array_column($ruleData, 'invitee');
        if (array_diff(array_column($inviteeData, 'award_type'), [Constants::AWARD_TYPE_TIME, Constants::AWARD_TYPE_GOLD_LEAF])) {
            throw new RunTimeException(['award_type_is_error']);
        }
        if (min(array_column($inviteeData, 'award_amount')) < 0) {
            throw new RunTimeException(['award_amount_is_greater_0']);
        }
        //邀请人身份检测
        if (empty(self::checkInviteeStatus($inviteeStatus))) {
            throw new RunTimeException(['invitee_status_is_error']);
        }
        foreach ($ruleData as $nv) {
            //奖励时间范围检测
            if (empty($nv['min_time']) || ($nv['min_time'] < 0)) {
                throw new RunTimeException(['min_value_is_error']);
            }

            if (empty($nv['max_time']) || ($nv['max_time'] <= 0) || ($nv['max_time'] > 3650) || ($nv['min_time'] >= $nv['max_time'])) {
                throw new RunTimeException(['max_value_is_error']);
            }
            $normalRule[] = [
                'type' => ReferralRulesRewardModel::REWARD_RULE_TYPE_NORMAL,
                //奖励条件
                'reward_condition' => json_encode([
                    'package_duration_min' => $nv['min_time'],
                    'package_duration_max' => $nv['max_time'],
                ]),
                //奖励明细
                'reward_details' => json_encode([
                    //受邀人
                    'invited' => [
                        [
                            'award_type' => (int)$nv['invited']['award_type'],
                            'award_amount' => isset($nv['invited']['award_amount']) ? (int)$nv['invited']['award_amount'] : 0,
                        ],
                    ],
                    //邀请人
                    'invitee' => [
                        [
                            'award_type' => (int)$nv['invitee']['award_type'],
                            'award_amount' => isset($nv['invitee']['award_amount']) ? (int)$nv['invitee']['award_amount'] : 0,
                        ],
                    ],
                ]),
                //受邀人限制条件
                'restrictions' => json_encode([
                    'refund_limit_min_days' => isset($nv['days']) ? (int)$nv['days'] : 0,//受邀人xx天内未退费
                ]),
                //邀请人身份
                'invitee_status' => Util::formatEnumToBit($nv['invitee_status']),
                //规则状态
                'status' => $nv['status'],
            ];
        }
        return $normalRule;

    }

    /**
     * 付费体验卡奖励规则检测
     * @param $ruleData
     * @return array
     * @throws RunTimeException
     */
    private static function trailRuleDataCheck($ruleData)
    {
        $trailRule = $inviteeStatus = [];
        $paramsInviteeStatus = array_column($ruleData, 'invitee_status');
        array_walk($paramsInviteeStatus, function ($rv) use (&$inviteeStatus) {
            $inviteeStatus = array_merge($rv, $inviteeStatus);
        });
        //检测当前奖励规则是否存在重复数据
        $inviteeStatusRepeatCheck = max(array_count_values($inviteeStatus));
        if (empty($inviteeStatusRepeatCheck) || ($inviteeStatusRepeatCheck >= 2)) {
            throw new RunTimeException(['invitee_status_is_repeat']);
        }
        //邀请人身份检测
        if (empty(self::checkInviteeStatus($inviteeStatus))) {
            throw new RunTimeException(['invitee_status_is_error']);
        }
        //奖励类型检测
        $inviteeData = array_column($ruleData, 'invitee');
        if (array_diff(array_column($inviteeData, 'award_type'), [Constants::AWARD_TYPE_TIME, Constants::AWARD_TYPE_GOLD_LEAF])) {
            throw new RunTimeException(['award_type_is_error']);
        }
        //奖励数量检测
        if (min(array_column($inviteeData, 'award_amount')) < 0) {
            throw new RunTimeException(['award_amount_is_greater_0']);
        }
        //限制条件天数检测
        if (min(array_column($ruleData, 'days')) < 0) {
            throw new RunTimeException(['first_buy_trail_days_is_greater_0']);
        }
        //练琴时长检测
        if (min(array_column($ruleData, 'play_times')) < 0) {
            throw new RunTimeException(['play_times_is_greater_0']);
        }
        foreach ($ruleData as $tv) {
            $trailRule[] = [
                'type' => ReferralRulesRewardModel::REWARD_RULE_TYPE_TRAIL,
                //奖励条件
                'reward_condition' => json_encode([
                    'is_first_trail' => true,
                ]),
                //奖励明细
                'reward_details' => json_encode([
                    //邀请人
                    'invitee' => [
                        [
                            'award_type' => (int)$tv['invitee']['award_type'],
                            'award_amount' => isset($tv['invitee']['award_amount']) ? (int)$tv['invitee']['award_amount'] : 0,//奖励数量：不同的奖励类型，对应不同的单位
                        ],
                    ],
                ]),
                //受邀人限制条件
                'restrictions' => json_encode([
                    'days' => isset($tv['days']) ? (int)$tv['days'] : 0,//受邀人首次购买体验卡xx天内
                    'play_times' => isset($tv['play_times']) ? (int)$tv['play_times'] : 0,//app练琴时长
                ]),
                //邀请人身份
                'invitee_status' => Util::formatEnumToBit($tv['invitee_status']),
                //规则状态
                'status' => $tv['status'],
            ];
        }
        return $trailRule;
    }

    /**
     * @param $inviteeStatus
     * @return bool
     */
    private static function checkInviteeStatus($inviteeStatus)
    {
        //邀请人身份检测
        if (array_diff($inviteeStatus, [
            Constants::REFERRAL_INVITER_ROOT,
            Constants::REFERRAL_INVITER_STATUS_REGISTER,
            Constants::REFERRAL_INVITER_STATUS_TRAIL,
            Constants::REFERRAL_INVITER_STATUS_TRAIL_EXPIRE,
            Constants::REFERRAL_INVITER_STATUS_NORMAL,
            Constants::REFERRAL_INVITER_STATUS_NORMAL_EXPIRE,])) {
            return false;
        }
        return true;
    }
}
