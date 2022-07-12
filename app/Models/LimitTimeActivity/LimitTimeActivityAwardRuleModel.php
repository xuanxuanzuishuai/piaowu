<?php
/**
 * 限时活动奖励规则
 */

namespace App\Models\LimitTimeActivity;

use App\Libs\MysqlDB;
use App\Models\Model;

class LimitTimeActivityAwardRuleModel extends Model
{
    public static $table = 'limit_time_activity_award_rule';

    /**
     * 获取活动奖励顾泽
     * @param array $activityId
     * @return array
     */
    public static function getActivityAwardRuleList(array $activityId): array
    {
        //获取数据
        $groupData = [];
        $db = MysqlDB::getDB();
        $data = $db->select(self::$table, ['id','activity_id', 'task_num', 'award_amount', 'award_type'],
            ['activity_id' => $activityId]);
        foreach ($data as $v) {
            $groupData[$v['activity_id']][] = $v;
        }
        return $groupData;
    }

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
