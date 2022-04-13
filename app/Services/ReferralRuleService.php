<?php


namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\Dss\DssStudentModel;
use App\Models\OperationActivityModel;
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
        $baseData = self::ruleBaseDataCheck($params['name'], $params['start_time'], $params['end_time'], $params['remark']);
        //付费体验卡奖励规则检测
        $trailRuleData = self::trailRuleDataCheck($params['trail_rule']);
        //付费正式时长奖励规则检测
        $normalRule = self::normalRuleDataCheck($params['normal_rule']);
        $addRes = ReferralRulesModel::addRule($baseData, $trailRuleData, $normalRule, $operatorId);
        if (empty($addRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        AdminOpLogService::opLogAdd($operatorId, [ReferralRulesModel::$table => $baseData], [ReferralRulesModel::$table => ['data_id' => $addRes]]);
        return $addRes;
    }


    /**
     * 编辑转介绍活动奖励规则
     * 1. 未启用状态下，可编辑所有字段。
     * 2. 启用、禁用状态下可编辑备注，其他字段置灰或者展示文本即可
     * @param $params
     * @param $operatorId
     * @return bool|int|mixed|null|string
     * @throws RunTimeException
     */
    public static function update($params, $operatorId)
    {
        $ruleData = ReferralRulesModel::getRecord(['id' => $params['rule_id']], ['id(data_id)', 'status', 'name', 'type', 'start_time', 'end_time', 'remark']);
        if (empty($ruleData)) {
            throw new RunTimeException(['record_not_found']);
        }
        $newData = $oldData = [];
        if (in_array($ruleData['status'], [OperationActivityModel::ENABLE_STATUS_DISABLE, OperationActivityModel::ENABLE_STATUS_ON])) {
            $updateRes = ReferralRulesModel::updateRecord($params['rule_id'], ['remark' => $params['remark'], 'update_time' => time()]);
            $newData = [ReferralRulesModel::$table => ['remark' => $params['remark']]];
            $oldData = [ReferralRulesModel::$table => ['remark' => $ruleData['remark'], 'data_id' => $params['rule_id']]];
        } elseif ($ruleData['status'] == OperationActivityModel::ENABLE_STATUS_OFF) {
            //基础数据
            $baseData = self::ruleBaseDataCheck($params['name'], $params['start_time'], $params['end_time'], $params['remark']);
            //付费体验卡奖励规则检测
            $trailRuleData = self::trailRuleDataCheck($params['trail_rule']);
            //付费正式时长奖励规则检测
            $normalRule = self::normalRuleDataCheck($params['normal_rule']);
            $updateRes = ReferralRulesModel::updateRule($params['rule_id'], $baseData, $trailRuleData, $normalRule, $operatorId);
            $newData = [ReferralRulesModel::$table => $baseData];
            $oldData = [ReferralRulesModel::$table => $ruleData];
        }
        if (empty($updateRes)) {
            throw new RunTimeException(['update_failure']);
        }
        AdminOpLogService::opLogAdd($operatorId, $newData, $oldData);

        return $updateRes;
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
    private static function ruleBaseDataCheck($name, $startTime, $endTime, $remark, $ruleType = ReferralRulesModel::TYPE_AI_STUDENT_REFEREE)
    {
        $time = time();
        $startTime = strtotime($startTime);
        $endTime = strtotime($endTime);
        //时间关系检测
        if (($endTime <= $time) || ($endTime <= $startTime)) {
            throw new RunTimeException(['end_time_error']);
        }
        if (($startTime <= $time)) {
            throw new RunTimeException(['start_time_must_greater_than_current_time']);
        }
        $baseData = [
            'name' => trim($name),
            'type' => $ruleType,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'remark' => Util::textEncode(trim($remark)),
        ];
        return $baseData;
    }

    /**
     * 付费正式时长奖励规则检测
     * @param $ruleData
     * @return array
     * @throws RunTimeException
     */
    private static function normalRuleDataCheck($ruleData)
    {
        //付费正式时长奖励规则检测
        $conflictCheck = self::normalRuleConflictCheck($ruleData);
        if (empty($conflictCheck)) {
            throw new RunTimeException(['invited_status_and_reward_condition_is_repeat']);

        }
        $normalRule = $invitedStatus = [];
        $paramsInvitedStatus = array_column($ruleData, 'invited_status');
        array_walk($paramsInvitedStatus, function ($rv) use (&$invitedStatus) {
            $invitedStatus = array_merge($rv, $invitedStatus);
        });
        $invitedStatus = array_unique($invitedStatus);
        //受邀人&邀请人奖励类型/数量检测
        $invitedAwardType = array_column($ruleData, 'invited_award_type');
        $inviteeAwardType = array_column($ruleData, 'invitee_award_type');
        if (array_diff(array_merge($inviteeAwardType, $invitedAwardType), [Constants::AWARD_TYPE_TIME, Constants::AWARD_TYPE_GOLD_LEAF])) {
            throw new RunTimeException(['award_type_is_error']);
        }
        $awardAmount = array_merge(array_column($ruleData, 'invited_award_amount'), array_column($ruleData, 'invitee_award_amount'));
        if (min($awardAmount) < 0) {
            throw new RunTimeException(['award_amount_must_gt_0']);
        }
        if (max($awardAmount) > 99999) {
            throw new RunTimeException(['award_amount_must_lt_99999']);
        }
        //邀请人身份检测
        if (empty(self::checkInviteeStatus($invitedStatus))) {
            throw new RunTimeException(['invited_status_is_error']);
        }
        foreach ($ruleData as $nv) {
            //奖励时间范围检测
            if ((int)$nv['package_duration_min'] < 0) {
                throw new RunTimeException(['min_value_is_error']);
            }

            if (empty($nv['package_duration_max']) || ($nv['package_duration_max'] <= 0) || ($nv['package_duration_max'] > 3650) || ($nv['package_duration_min'] >= $nv['package_duration_max'])) {
                throw new RunTimeException(['max_value_is_error']);
            }
            $normalRule[] = [
                'type' => ReferralRulesRewardModel::REWARD_RULE_TYPE_NORMAL,
                //奖励条件
                'reward_condition' => json_encode([
                    'package_duration_min' => $nv['package_duration_min'],
                    'package_duration_max' => $nv['package_duration_max'],
                ]),
                //奖励明细
                'reward_details' => json_encode([
                    //邀请人
                    'invited' => [
                        [
                            'award_type' => $nv['invited_award_type'],
                            'award_amount' => (int)$nv['invited_award_amount'],
                        ],
                    ],
                    //受邀人
                    'invitee' => [
                        [
                            'award_type' => $nv['invitee_award_type'],
                            'award_amount' => (int)$nv['invitee_award_amount'],
                        ],
                    ],
                ]),
                //受邀人限制条件
                'restrictions' => json_encode([
                    'refund_limit_min_days' => isset($nv['refund_limit_min_days']) ? (int)$nv['refund_limit_min_days'] : 0,//受邀人xx天内未退费
                ]),
                //邀请人身份
                'invited_status' => Util::formatEnumToBit($nv['invited_status']),
                //规则状态
                'status' => $nv['status'],
            ];
        }
        return $normalRule;

    }

    /**
     * 邀请人身份和奖励条件设置时间范围冲突检测
     * @param $ruleData
     * @return bool
     */
    private static function normalRuleConflictCheck($ruleData)
    {
        $invitedStatusAndPackageDurationData = [];
        foreach ($ruleData as $nv) {
            array_map(function ($isv) use (&$invitedStatusAndPackageDurationData, $nv) {
                $rangeCount = $nv['package_duration_max'] - $nv['package_duration_min'] + 1;
                $rangeFillData = [];
                for ($i = $nv['package_duration_min']; $i <= $nv['package_duration_max']; $i++) {
                    $rangeFillData[$i] = $i;
                }
                if (empty($invitedStatusAndPackageDurationData[$isv])) {
                    $invitedStatusAndPackageDurationData[$isv]['list'] = [];
                    $invitedStatusAndPackageDurationData[$isv]['range_count'] = 0;
                }
                $invitedStatusAndPackageDurationData[$isv]['list'] = array_merge($invitedStatusAndPackageDurationData[$isv]['list'], $rangeFillData);
                $invitedStatusAndPackageDurationData[$isv]['range_count'] += $rangeCount;
            }, $nv['invited_status']);
        }
        foreach ($invitedStatusAndPackageDurationData as $pdv) {
            if (count(array_unique($pdv['list'])) != $pdv['range_count']) {
                return false;
                break;
            }
        }
        return true;
    }


    /**
     * 付费体验卡奖励规则检测
     * @param $ruleData
     * @return array
     * @throws RunTimeException
     */
    private static function trailRuleDataCheck($ruleData)
    {
        $trailRule = $invitedStatus = [];
        $paramsInvitedStatus = array_column($ruleData, 'invited_status');
        array_walk($paramsInvitedStatus, function ($rv) use (&$invitedStatus) {
            $invitedStatus = array_merge($rv, $invitedStatus);
        });
        //检测当前奖励规则是否存在重复数据
        $invitedStatusRepeatCheck = max(array_count_values($invitedStatus));
        if (empty($invitedStatusRepeatCheck) || ($invitedStatusRepeatCheck >= 2)) {
            throw new RunTimeException(['invited_status_is_repeat']);
        }
        //邀请人身份检测
        if (empty(self::checkInviteeStatus($invitedStatus))) {
            throw new RunTimeException(['invited_status_is_error']);
        }
        //奖励类型检测
        $invitedAwardType = array_column($ruleData, 'invited_award_type');
        if (array_diff($invitedAwardType, [Constants::AWARD_TYPE_TIME, Constants::AWARD_TYPE_GOLD_LEAF])) {
            throw new RunTimeException(['award_type_is_error']);
        }
        //奖励数量检测
        $invitedAwardAmount = array_column($ruleData, 'invited_award_amount');
        if (min($invitedAwardAmount) < 0) {
            throw new RunTimeException(['award_amount_must_gt_0']);
        }
        if (max($invitedAwardAmount) > 99999) {
            throw new RunTimeException(['award_amount_must_lt_99999']);
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
                    'invited' => [
                        [
                            'award_type' => (int)$tv['invited_award_type'],
                            'award_amount' => (int)$tv['invited_award_amount'],//奖励数量：不同的奖励类型，对应不同的单位
                        ],
                    ],
                ]),
                //受邀人限制条件
                'restrictions' => json_encode([
                    'days' => isset($tv['days']) ? (int)$tv['days'] : 0,//受邀人首次购买体验卡xx天内
                    'play_times' => isset($tv['play_times']) ? (int)$tv['play_times'] : 0,//app练琴时长
                ]),
                //邀请人身份
                'invited_status' => Util::formatEnumToBit($tv['invited_status']),
                //规则状态
                'status' => $tv['status'],
            ];
        }
        return $trailRule;
    }

    /**
     * 检测邀请人身份状态
     * @param $inviteeStatus
     * @return bool
     */
    private static function checkInviteeStatus($inviteeStatus)
    {
        //邀请人身份检测
        if (array_diff($inviteeStatus, [
            Constants::REFERRAL_INVITER_STATUS_REGISTER,
            Constants::REFERRAL_INVITER_STATUS_TRAIL,
            Constants::REFERRAL_INVITER_STATUS_TRAIL_EXPIRE,
            Constants::REFERRAL_INVITER_STATUS_NORMAL,
            Constants::REFERRAL_INVITER_STATUS_NORMAL_EXPIRE,])) {
            return false;
        }
        return true;
    }

    /**
     * 获取规则详情
     * @param $ruleId
     * @return array
     * @throws RunTimeException
     */
    public static function detail($ruleId)
    {
        $data = [
            'name' => '',
            'start_time' => '',
            'end_time' => '',
            'remark' => '',
            'status' => '',
            'trail_rule' => [],
            'normal_rule' => [],
        ];
        //基础数据
        $detailData = ReferralRulesModel::getRecord(['id' => $ruleId], ['name', 'start_time', 'end_time', 'remark', 'status']);
        if (empty($detailData)) {
            throw new RunTimeException(['record_not_found']);
        }
        $data['name'] = $detailData['name'];
        $data['start_time'] = date("Y-m-d H:i:s", $detailData['start_time']);
        $data['end_time'] = date("Y-m-d H:i:s", $detailData['end_time']);
        $data['remark'] = Util::textDecode($detailData['remark']);
        $data['status'] = $detailData['status'];
        //奖励规则数据
        $awardData = ReferralRulesRewardModel::getRecords(
            [
                'rule_id' => $ruleId,
                'status' => [
                    OperationActivityModel::ENABLE_STATUS_OFF,
                    OperationActivityModel::ENABLE_STATUS_ON
                ],
            ],
            [
                'type',
                'invited_status',
                'status',
                'reward_details',
                'reward_condition',
                'restrictions',
            ]);

        if (empty($awardData)) {
            return [];
        }
        foreach ($awardData as $dk => $dv) {
            $restrictions = json_decode($dv['restrictions'], true);
            $rewardCondition = json_decode($dv['reward_condition'], true);
            $rewardDetails = json_decode($dv['reward_details'], true);
            $tmpData = array_merge($rewardCondition, $restrictions);
            $tmpData['invited_award_type'] = (string)$rewardDetails['invited'][0]['award_type'];
            $tmpData['invited_award_amount'] = $rewardDetails['invited'][0]['award_amount'];
            $tmpData['invited_status'] = array_column(self::formatInvitedStatus($dv['invited_status']), 'invited_status');
            $tmpData['status'] = $dv['status'];
            if ($dv['type'] == ReferralRulesRewardModel::REWARD_RULE_TYPE_TRAIL) {
                //体验卡
                $data['trail_rule'][] = $tmpData;
            } elseif ($dv['type'] == ReferralRulesRewardModel::REWARD_RULE_TYPE_NORMAL) {
                //正式时长
                $tmpData['invitee_award_type'] = (string)$rewardDetails['invitee'][0]['award_type'];
                $tmpData['invitee_award_amount'] = $rewardDetails['invitee'][0]['award_amount'];
                $data['normal_rule'][] = $tmpData;
            }
        }
        return $data;
    }

    /**
     * 格式化处理邀请人身份状态
     * @param $invitedStatus
     * @return array
     */
    public static function formatInvitedStatus($invitedStatus)
    {
        $dictConfig = DictConstants::getSet(DictConstants::REFERRAL_RULE_INVITED_STATUS);
        $invitedStatusFormat = [];
        foreach ($dictConfig as $k => $v) {
            if (intval($invitedStatus) & intval($k)) {
                $invitedStatusFormat[$k] = [
                    'invited_status' => (string)$k,
                    'invited_status_name' => $v
                ];
            }
        }
        return $invitedStatusFormat;
    }

    /**
     * 获取规则列表
     * @param $params
     * @return mixed
     */
    public static function list($params)
    {
        $where = ['id[>]' => 0];
        //规则ID
        if (!empty($params['rule_id'])) {
            $where['id'] = (int)$params['rule_id'];
        }
        //规则名称
        if (!empty($params['rule_name'])) {
            $where['name[~]'] = trim($params['rule_name']);
        }
        //规则类型
        if (!empty($params['rule_type'])) {
            $where['type'] = $params['rule_type'];
        }
        //规则启用状态
        if (!empty($params['enable_status'])) {
            $where['status'] = $params['enable_status'];
        }
        //规则时间状态
        if (!empty($params['time_status'])) {
            $where = array_merge($where, OperationActivityModel::timeStatusMapToSqlWhere($params['time_status']));
        }
        $data = ReferralRulesModel::list($where, $params['page'], $params['count']);
        return self::formatListData($data);
    }

    /**
     * 格式化列表信息
     * @param $data
     * @return mixed
     */
    private static function formatListData($data)
    {
        $dictConfig = DictConstants::getTypesMap([DictConstants::REFERRAL_RULE_TYPE['type'], DictConstants::ACTIVITY_ENABLE_STATUS['type'], DictConstants::ACTIVITY_TIME_STATUS['type']]);
        foreach ($data['list'] as &$lv) {
            $lv['rule_type_name'] = $dictConfig[DictConstants::REFERRAL_RULE_TYPE['type']][$lv['type']]['value'];
            $lv['enable_status_name'] = $dictConfig[DictConstants::ACTIVITY_ENABLE_STATUS['type']][$lv['status']]['value'];
            $lv['create_time'] = date('Y-m-d H:i:s', $lv['create_time']);
            $lv['time_status_name'] = $dictConfig[DictConstants::ACTIVITY_TIME_STATUS['type']][OperationActivityModel::dataMapToTimeStatus($lv['start_time'], $lv['end_time'])]['value'];
            $lv['start_time'] = date('Y-m-d H:i:s', $lv['start_time']);
            $lv['end_time'] = date('Y-m-d H:i:s', $lv['end_time']);
            $lv['remark'] = Util::textDecode($lv['remark']);
        }
        return $data;
    }

    /**
     * 规则启用状态修改
     * @param $ruleId
     * @param $enableStatus
     * @param $operatorId
     * @return bool
     * @throws RunTimeException
     */
    public static function updateEnableStatus($ruleId, $enableStatus, $operatorId)
    {
        $ruleData = ReferralRulesModel::getRecord(['id' => $ruleId], ['status', 'type', 'start_time', 'end_time']);
        if (empty($ruleData)) {
            throw new RunTimeException(['record_not_found']);
        }
        if ($ruleData['status'] == $enableStatus) {
            throw new RunTimeException(['nothing_change']);
        }
        if ($ruleData['status'] > $enableStatus) {
            throw new RunTimeException(['stop_reverse_operation']);
        }
        if ($ruleData['status'] == OperationActivityModel::ENABLE_STATUS_DISABLE) {
            throw new RunTimeException(['activity_disable_not_start']);
        }
        // 如果是启用 检查时间是否冲突
        if ($enableStatus == OperationActivityModel::ENABLE_STATUS_ON) {
            $ruleConflictData = ReferralRulesModel::getRecords([
                'id[!]' => $ruleId,
                'start_time[<=]' => $ruleData['end_time'],
                'end_time[>=]' => $ruleData['start_time'],
                'type' => $ruleData['type'],
                'status' => OperationActivityModel::ENABLE_STATUS_ON
            ], ['id']);
            if (!empty($ruleConflictData)) {
                throw new RunTimeException(['activity_time_conflict_id', '', '', array_column($ruleConflictData, 'id')]);
            }
        }
        $updateRes = ReferralRulesModel::updateRecord($ruleId, ['status' => $enableStatus, 'update_time' => time()]);
        if (empty($updateRes)) {
            throw new RunTimeException(['update_failure']);
        }
        AdminOpLogService::opLogAdd(
            $operatorId,
            [ReferralRulesModel::$table => ['status' => $enableStatus],],
            [ReferralRulesModel::$table => ['status' => $ruleData['status'], 'data_id' => $ruleId]]);
        return true;
    }


    /**
     * 复制转介绍规则
     * @param $ruleId
     * @param $operatorId
     * @return int
     * @throws RunTimeException
     */
    public static function copy($ruleId, $operatorId)
    {
        //基础数据
        $baseData = ReferralRulesModel::getRecord(['id' => $ruleId], ['name', 'type', 'start_time', 'end_time', 'remark']);
        if (empty($baseData)) {
            throw new RunTimeException(['record_not_found']);
        }
        //奖励规则数据
        $ruleRewardData = ReferralRulesRewardModel::getRecords(
            [
                'rule_id' => $ruleId,
                'status' => [
                    OperationActivityModel::ENABLE_STATUS_ON,
                    OperationActivityModel::ENABLE_STATUS_OFF
                ],
                'ORDER' => ["id" => "ASC"]
            ],
            [
                'type',
                'invited_status',
                'status',
                'reward_details',
                'reward_condition',
                'restrictions',
            ]);
        if (empty($ruleRewardData)) {
            throw new RunTimeException(['record_not_found']);
        }
        $copyRes = ReferralRulesModel::copyRule($baseData, $ruleRewardData, $operatorId);
        if (empty($copyRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        return $copyRes;
    }

    /**
     * 转介绍规则：获取学生作为受邀人/邀请人可得奖励的最大值
     * @param $studentId
     * @param $identityType
     * @param $awardType
     * @return int|mixed
     */
    public static function getStudentMaxReferralAwardData($studentId, $identityType, $awardType)
    {
        $awardMaxAmount = 0;
        $studentData = DssStudentModel::getRecord(['id' => $studentId], ['has_review_course', 'sub_end_date', 'id']);
        $studentReferralIdentityStatus = TraitUserRefereeService::getReferralStudentIdentity($studentData);

        //正式时长&体验卡奖励数据
        $awardData = ReferralRulesModel::getCurrentRunRuleInfoByInviteStudentIdentity($studentReferralIdentityStatus, ReferralRulesModel::TYPE_AI_STUDENT_REFEREE);
        if (empty($awardData)) {
            return $awardMaxAmount;
        }
        $rewardDetails = array_column($awardData['rule_list'], 'reward_details');
        $rewardDetailsFormat = $awardsData = [];
        foreach ($rewardDetails as $akl => $avl) {
            $rewardDetailsFormat[] = json_decode($avl, true);
        };
        $invitedAwards = array_column($rewardDetailsFormat, 'invited');
        $inviteeAwards = array_column($rewardDetailsFormat, 'invitee');
        //邀请人
        if (($identityType == Constants::STUDENT_ID_INVITER) && !empty($invitedAwards)) {
            foreach ($invitedAwards as $k => $v) {
                foreach ($v as $vv) {
                    $awardsData[$vv['award_type']][] = $vv['award_amount'];
                }
            }
        } else if (($identityType == Constants::STUDENT_ID_INVITEE) && !empty($inviteeAwards)) {
            foreach ($inviteeAwards as $ek => $ev) {
                foreach ($ev as $evv) {
                    $awardsData[$evv['award_type']][] = $evv['award_amount'];
                }
            }
        }
        return empty($awardsData[$awardType]) ? $awardMaxAmount : max($awardsData[$awardType]);
    }
}
