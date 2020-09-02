<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\LeadsPoolModel;
use App\Models\LeadsPoolOpLogModel;
use App\Models\LeadsPoolRuleModel;
use App\Services\LeadsPool\CacheManager;

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
        $time = time();
        //池子添加数据
        $poolInsertData = [
            'name' => $params['pool_name'],
            'target_type' => $params['target_type'],
            'target_set_id' => ($params['target_type'] == LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) ? $params['dept_id'] : 0,
            'operator' => $operatorId,
            'create_time' => $time,
            'status' => LeadsPoolModel::LEADS_POOL_STATUS_ABLE,
            'type' => LeadsPoolModel::LEADS_POOL_TYPE_SELF_CREATE,
        ];
        //池子分配规则
        $poolRulesInsertData = array_map(function ($rv) use ($operatorId, $time, $params) {
            if (empty($rv['weight']) || empty($rv['target_id'])) {
                throw  new RunTimeException(['weight_or_target_id_required']);
            } else {
                return [
                    'weight' => $rv['weight'],
                    'target_type' => $params['target_type'],
                    'target_id' => $rv['target_id'],
                    'operator' => $operatorId,
                    'create_time' => $time,
                    'status' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_ABLE,
                ];
            }
        }, $params['alloc_rules']);
        //添加数据
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $insertResPoolId = LeadsPoolModel::addLeadsPoolAndRuleData($poolInsertData, $poolRulesInsertData, $operatorId);
        if (empty($insertResPoolId)) {
            $db->rollBack();
            throw  new RunTimeException(['insert_failure']);
        } else {
            $db->commit();
        }
        //更新分配规则缓存信息
        CacheManager::delPoolRulesCache($insertResPoolId, date("Ymd", $time));
        return true;
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
        //检测是否允许修改
        if ($params['target_type'] == LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_PEOPLE && empty($params['dept_id'])) {
            throw  new RunTimeException(['dept_id_is_required']);
        }

        //获取池子的原始数据
        $leadsPoolOldData = self::detail($params['pool_id']);
        if (empty($leadsPoolOldData)) {
            throw  new RunTimeException(['leads_pool_id_error']);
        }
        $time = time();
        $trackId = Util::makeUniqueId();
        $opLog = $poolUpdateData = $poolRulesUpdateData = $poolRulesAddData = $ableOldRulesTargetIds = [];
        //修改线索池子:总池不可以修改名称和分配方式
        if ($leadsPoolOldData[0]['detail']['type'] != LeadsPoolModel::LEADS_POOL_TYPE_PUBLIC) {
            $poolUpdateData = [
                'name' => $params['pool_name'],
                'target_type' => $params['target_type'],
                'target_set_id' => ($params['target_type'] == LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) ? $params['dept_id'] : 0,
                'type' => LeadsPoolModel::LEADS_POOL_TYPE_SELF_CREATE,
            ];
            //检测数据是否发生变化
            if (!empty(array_diff_assoc($poolUpdateData, $leadsPoolOldData[0]['detail']))) {
                $opLog[] = [
                    'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_UPDATE,
                    'detail' => json_encode(array_merge($poolUpdateData, ['pool_id' => $params['pool_id']])),
                    'create_time' => $time,
                    'operator' => $operatorId,
                    'track_id' => $trackId,
                ];
            } else {
                $poolUpdateData = [];
            };
        }
        //修改线索池子分配规则
        $oldRules = array_column($leadsPoolOldData[0]['rules'], null, 'rule_id');
        $ableOldRulesTargetIds = array_column($leadsPoolOldData[0]['rules'], 'target_id');
        if (is_array($params['alloc_rules']['update']) && !empty($params['alloc_rules']['update']) && !empty($oldRules)) {
            //检测数据是否发生变化
            foreach ($params['alloc_rules']['update'] as $rk => $rv) {
                if (empty($rv['weight']) || empty($rv['target_id']) || empty($rv['status']) || empty($rv['rule_id'])) {
                    throw  new RunTimeException(['leads_pool_rules_params_required']);
                }
                //标记删除的规则ID
                if ($rv['status'] == LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_DEL) {
                    unset($ableOldRulesTargetIds[array_search($rv['target_id'], $ableOldRulesTargetIds)]);
                }
                $tmpPoolRulesUpdateData = [
                    'weight' => $rv['weight'],
                    'target_type' => $params['target_type'],
                    'target_id' => $rv['target_id'],
                    'status' => $rv['status'],
                ];
                if (!empty(array_diff_assoc($tmpPoolRulesUpdateData, $oldRules[$rv['rule_id']]))) {
                    $opLog[] = [
                        'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_RULE_UPDATE,
                        'detail' => json_encode(array_merge($tmpPoolRulesUpdateData, ['operator' => $operatorId, 'rule_id' => $rv['rule_id']])),
                        'create_time' => $time,
                        'operator' => $operatorId,
                        'track_id' => $trackId,
                    ];
                    $poolRulesUpdateData[] = [
                        'rule_id' => $rv['rule_id'],
                        'update_data' => $tmpPoolRulesUpdateData,
                    ];
                };
            }
        }
        //增加线索池子分配规则
        if (is_array($params['alloc_rules']['add']) && !empty($params['alloc_rules']['add'])) {
            $poolRulesAddData = array_map(function ($rv) use ($operatorId, $time, $params, $ableOldRulesTargetIds) {
                if (empty($rv['weight']) || empty($rv['target_id']) || empty($rv['status'])) {
                    throw  new RunTimeException(['leads_pool_rules_params_required']);
                }
                if (array_search($rv['target_id'], $ableOldRulesTargetIds) !== false) {
                    throw  new RunTimeException(['leads_pool_target_id_repeat']);
                }
                return [
                    'pool_id' => $params['pool_id'],
                    'weight' => $rv['weight'],
                    'target_type' => $params['target_type'],
                    'target_id' => $rv['target_id'],
                    'operator' => $operatorId,
                    'create_time' => $time,
                    'status' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_ABLE,
                ];
            }, $params['alloc_rules']['add']);
            $opLog[] = [
                'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_RULE_ADD,
                'detail' => json_encode($poolRulesAddData),
                'create_time' => $time,
                'operator' => $operatorId,
                'track_id' => $trackId,
            ];
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $updateRes = LeadsPoolModel::updateLeadsPoolAndRuleData($params['pool_id'], $opLog, $poolUpdateData, $poolRulesUpdateData, $poolRulesAddData);
        if (empty($updateRes)) {
            $db->rollBack();
            throw  new RunTimeException(['update_failure']);
        } else {
            $db->commit();
        }
        //更新分配规则缓存信息
        CacheManager::delPoolRulesCache($params['pool_id'], date("Ymd", $time));
        return true;
    }


    /**
     * 修改线索分配池状态
     * @param $poolId
     * @param $poolStatus
     * @param $operatorId
     * @return int|null
     * @throws RunTimeException
     */
    public static function updatePoolStatus($poolId, $poolStatus, $operatorId)
    {
        //检测线索池是否可以修改
        if (empty(self::checkPoolIsCanUpdate($poolId))) {
            throw  new RunTimeException(['leads_pool_stop_update']);
        }
        $updateRes = LeadsPoolModel::updateRecord($poolId, ['status' => $poolStatus], false);
        if (empty($updateRes)) {
            throw  new RunTimeException(['update_failure']);
        }
        //记录操作日志
        LeadsPoolOpLogModel::insertRecord([
            'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_UPDATE,
            'detail' => json_encode(['pool_id' => $poolId, 'status' => $poolStatus]),
            'create_time' => time(),
            'operator' => $operatorId,
            'track_id' => Util::makeUniqueId(),
        ], false);
        //更新分配规则缓存信息
        CacheManager::delPoolRulesCache($poolId, date("Ymd"));
        return true;
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
