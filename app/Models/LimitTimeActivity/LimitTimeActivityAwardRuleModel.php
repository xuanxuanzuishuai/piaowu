<?php
/**
 * 限时活动奖励规则
 */

namespace App\Models\LimitTimeActivity;

use App\Models\Model;

class LimitTimeActivityAwardRuleModel extends Model
{
    public static $table = 'limit_time_activity_award_rule';

    /**
     * 获取奖励规则
     * @param $activityId
     * @return array
     */
    public static function getActivityAwardRule($activityId)
    {
        return self::getRecords(['activity_id' => $activityId]);
    }
}
