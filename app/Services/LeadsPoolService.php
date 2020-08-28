<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\LeadsPoolModel;

class LeadsPoolService
{

    /**
     * 添加线索分配池
     * @param $params
     * @param $operatorId
     * @return int
     * @throws RunTimeException
     */
    public static function add($params, $operatorId)
    {
        if ($params['target_type'] == LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_PEOPLE && empty($params['dept_id'])) {
            throw  new RunTimeException(['dept_id_is_required']);
        }
        return LeadsPoolModel::addLeadsPoolAndRuleData($params, $operatorId);
    }

    /**
     * 修改线索分配池
     * @param $params
     * @param $operatorId
     * @return int
     * @throws RunTimeException
     */
    public static function update($params, $operatorId)
    {
        if ($params['target_type'] == LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_PEOPLE && empty($params['dept_id'])) {
            throw  new RunTimeException(['dept_id_is_required']);
        }
        LeadsPoolModel::updateLeadsPoolAndRuleData($params, $operatorId);
        return true;
    }


    /**
     * 修改线索分配池状态
     * @param $poolId
     * @param $poolStatus
     * @return int|null
     * @throws RunTimeException
     */
    public static function updatePoolStatus($poolId, $poolStatus)
    {
        //检测线索池是否可以修改
        if (empty(self::checkPoolIsCanUpdate($poolId))) {
            throw  new RunTimeException(['leads_pool_stop_update']);
        }
        return LeadsPoolModel::updateRecord($poolId, ['status' => $poolStatus], false);
    }

    /**
     * 获取线索分配池数据
     * @param $poolId
     * @return array|null
     */
    public static function detail($poolId)
    {
        $data = [];
        $leadPoolData = LeadsPoolModel::getLeadsPoolAndRuleData($poolId);
        if (!empty($leadPoolData)) {
            $data = array_values(LeadsPoolModel::formatPoolAndRuleData($leadPoolData));
        }
        //格式化数据
        return $data;
    }

    /**
     * 获取线索分配池列表
     * @param $page
     * @param $count
     * @param $status
     * @return array|null
     */
    public static function getPoolList($page, $count, $status = LeadsPoolModel::LEADS_POOL_STATUS_ABLE)
    {
        $list = [
            'list' => [],
            'count' => 0,
        ];
        $leadPoolCount = LeadsPoolModel::getCount(
            [
                'status' => $status,
            ]);
        if (empty($leadPoolCount)) {
            return $list;
        }
        $list['count'] = $leadPoolCount;
        $leadPoolList = LeadsPoolModel::getRecords(
            [
                'status' => $status,
                "ORDER" => ["type" => "ASC"],
                "LIMIT" => [($page - 1) * $count, $count],
            ],
            ["id", "name", "target_type", "target_set_id", "status", "type"],
            false);
        //格式化数据
        if (!empty($leadPoolList)) {
            $list['list'] = LeadsPoolModel::formatPoolListData($leadPoolList);
        }
        return $list;
    }


    /**
     * 检测线索池是否可以被修改
     * @param $poolId
     * @return bool
     */
    private static function checkPoolIsCanUpdate($poolId)
    {
        //错误的ID和总池禁止修改:false禁止修改 true允许修改
        $poolDetail = LeadsPoolModel::getById($poolId);
        if (empty($poolDetail) || ($poolDetail['type'] == LeadsPoolModel::LEADS_POOL_TYPE_PUBLIC)) {
            return false;
        }
        return true;
    }
}
