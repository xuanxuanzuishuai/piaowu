<?php
/**
 * 学生转介绍规则奖励详情数据表
 */

namespace App\Models;

class ReferralRulesRewardModel extends Model
{
    public static $table = "referral_rules_reward";

    //奖励规则类型:1付费体验卡奖励 2付费正式课包奖励
    const REWARD_RULE_TYPE_TRAIL = 1;
    const REWARD_RULE_TYPE_NORMAL = 2;
}
