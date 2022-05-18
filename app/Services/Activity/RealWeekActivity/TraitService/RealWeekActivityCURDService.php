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
        $searchWhere = [];
        !empty($where['activity_id']) && $searchWhere['w.activity_id'] = $where['activity_id'];
        !empty($where['name']) && $searchWhere['w.name[~]'] = trim($where['name']);
        !empty($where['OR']) && $searchWhere['OR'] = $where['OR'];
        !empty($where['enable_status']) && $searchWhere['w.enable_status'] = $where['enable_status'];
        $db = MysqlDB::getDB();
        // 获取总数
        $totalCount = $db->count(RealWeekActivityModel::$table . '(w)', $searchWhere);
        if (empty($totalCount)) {
            return [0, []];
        }
        // 获取列表
        $columns = ['w.activity_id', 'w.name'];
        $searchWhere['ORDER'] = ['w.id' => 'DESC'];
        !empty($where['LIMIT']) && $searchWhere['LIMIT'] = $where['LIMIT'];
        if ($where['share_poster_verify_status'] == RealSharePosterModel::VERIFY_STATUS_WAIT) {
            $join = [
                '[>]' . RealSharePosterModel::$table . '(sp)' => ['w.activity_id' => 'activity_id'],
            ];
            $searchWhere['GROUP'] = 'w.activity_id';
            $searchWhere['sp.verify_status'] = RealSharePosterModel::VERIFY_STATUS_WAIT;
            $list = $db->select(RealWeekActivityModel::$table . '(w)', $join, $columns, $searchWhere);
        } else {
            $list = $db->select(RealWeekActivityModel::$table . '(w)', $columns, $searchWhere);
        }

        return [intval($totalCount), is_array($list) ? $list : []];
    }
}