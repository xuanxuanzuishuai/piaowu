<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022/04/28
 * Time: 11:34
 */

namespace App\Services\Activity\WeekActivity\TraitService;

use App\Models\WeekActivityRuleVersionAbModel;

trait WeekActivityCURDService
{
    /**
     * 添加一个新的版本
     * @param $activityId
     * @param $abPosterInfo
     * @param $employeeId
     * @return int
     */
    private static function TraitAddOne($activityId, $abPosterInfo, $employeeId)
    {
        $id = WeekActivityRuleVersionAbModel::insertRecord([
            'activity_id' => $activityId,
            'rule_data' => json_encode($abPosterInfo),
            'operator_id' => $employeeId,
            'create_time' => time(),
        ]);
        return intval($id);
    }
}