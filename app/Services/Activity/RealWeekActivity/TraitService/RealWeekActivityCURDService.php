<?php
/**
 * Created by PhpStorm.
 * User: qingfeng.lian
 * Date: 2022/04/28
 * Time: 11:34
 */

namespace App\Services\Activity\RealWeekActivity\TraitService;

use App\Libs\MysqlDB;
use App\Models\RealSharePosterModel;
use App\Models\RealWeekActivityModel;
use App\Models\RealWeekActivityRuleVersionAbModel;
use Medoo\Medoo;

trait RealWeekActivityCURDService
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
        $id = RealWeekActivityRuleVersionAbModel::insertRecord([
            'activity_id' => $activityId,
            'rule_data'   => json_encode($abPosterInfo),
            'operator_id' => $employeeId,
            'create_time' => time(),
        ]);
        return intval($id);
    }

    /**
     * 获取审核截图搜索条件中下拉框的活动列表
     * @param $where
     * @return array
     */
    private static function TraitGetVerifySharePosterActivityList($where)
    {
        // 获取列表
        [$totalCount, $list] = RealWeekActivityModel::getActivityList($where);
        return [intval($totalCount), is_array($list) ? $list : []];
    }
}