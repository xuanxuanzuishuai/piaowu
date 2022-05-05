<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022/04/28
 * Time: 11:34
 */

namespace App\Services\Activity\RealWeekActivity;

use App\Models\RealWeekActivityRuleVersionAbModel;
use App\Services\Activity\RealWeekActivity\TraitService\RealWeekActivityCURDService;

class RealWeekActivityClientService
{
    use RealWeekActivityCURDService;

    /**
     * 创建一个新的版本
     * @param $activityId
     * @param $abPosterInfo
     * @param $employeeId
     * @return int
     */
    public static function createVersion($activityId, $abPosterInfo, $employeeId)
    {
        return self::TraitAddOne($activityId, $abPosterInfo, $employeeId);
    }

    /**
     * 获取当前版本
     * @param $activityId
     * @return int
     */
    public static function getCurrentVersion($activityId)
    {
        $version = RealWeekActivityRuleVersionAbModel::getRecord(['activity_id' => $activityId, 'ORDER' => ['id' => 'DESC']]);
        return $version['id'] ?? 0;
    }

}
