<?php
/**
 * 限时活动奖励规则版本
 */

namespace App\Models\LimitTimeActivity;

use App\Models\Model;

class LimitTimeActivityAwardRuleVersionModel extends Model
{
    public static $table = 'limit_time_activity_award_rule_version';

    /**
     * 获取奖励规则版本
     * @param $activityId
     * @return array
     */
    public static function getActivityAwardRuleVersion($activityId)
    {
        return self::getRecord(['activity_id' => $activityId, 'ORDER' => ['id' => 'DESC']]);
    }
}
