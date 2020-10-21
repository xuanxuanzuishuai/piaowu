<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Services;

use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\EmployeeModel;
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
     * @param $type
     * @return int
     * @throws RunTimeException
     */
    public static function add($params, $operatorId, $type = LeadsPoolModel::LEADS_POOL_TYPE_SELF_CREATE)
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
            'type' => $type,
        ];
        //池子分配规则
        $poolRulesInsertData = [];
        array_map(function ($rv) use ($operatorId, $time, $params, &$poolRulesInsertData) {
            if (empty($rv['weight']) || empty($rv['target_id'])) {
                throw  new RunTimeException(['weight_or_target_id_required']);
            }
            $weight = (int)$rv['weight'];
            if (($weight <= 0) || ($weight > 100)) {
                throw  new RunTimeException(['leads_pool_rules_weight_error']);
            }
            if (isset($poolRulesInsertData[$rv['target_id']])) {
                throw  new RunTimeException(['leads_pool_rules_target_id_repeat']);
            }
            $poolRulesInsertData[$rv['target_id']] = [
                'weight' => $rv['weight'],
                'target_type' => $params['target_type'],
                'target_id' => $rv['target_id'],
                'operator' => $operatorId,
                'create_time' => $time,
                'status' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_ABLE,
            ];

        }, $params['alloc_rules']);
        //添加数据
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $insertResPoolId = LeadsPoolModel::addLeadsPoolAndRuleData($poolInsertData, array_values($poolRulesInsertData), $operatorId);
        if (empty($insertResPoolId)) {
            $db->rollBack();
            throw  new RunTimeException(['insert_failure']);
        }
        //记录课管分配最大上限
        if (!empty($params['extra_data'])) {
            array_walk($params['extra_data'], function ($mn, $eid) {
                EmployeeModel::updateEmployee($eid, ['leads_max_nums' => $mn]);
            });
        }
        $db->commit();
        //更新分配规则缓存信息
        CacheManager::delPoolRulesCache($insertResPoolId, date("Ymd", $time));
        return $insertResPoolId;
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
                $weight = (int)$rv['weight'];
                if (($weight <= 0) || ($weight > 100)) {
                    throw  new RunTimeException(['leads_pool_rules_weight_error']);
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
            array_map(function ($rv) use ($operatorId, $time, $params, $ableOldRulesTargetIds, &$poolRulesAddData) {
                if (empty($rv['weight']) || empty($rv['target_id']) || empty($rv['status'])) {
                    throw  new RunTimeException(['leads_pool_rules_params_required']);
                }
                if (array_search($rv['target_id'], $ableOldRulesTargetIds) !== false) {
                    throw  new RunTimeException(['leads_pool_target_id_repeat']);
                }
                $weight = (int)$rv['weight'];
                if (($weight <= 0) || ($weight > 100)) {
                    throw  new RunTimeException(['leads_pool_rules_weight_error']);
                }
                if (isset($poolRulesAddData[$rv['target_id']])) {
                    throw  new RunTimeException(['leads_pool_rules_target_id_repeat']);
                }
                $poolRulesAddData[$rv['target_id']] = [
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
        $updateRes = LeadsPoolModel::updateLeadsPoolAndRuleData($params['pool_id'], $opLog, $poolUpdateData, $poolRulesUpdateData, array_values($poolRulesAddData));
        if (empty($updateRes)) {
            $db->rollBack();
            throw  new RunTimeException(['update_failure']);
        }
        //记录课管分配最大上限
        if (!empty($params['extra_data'])) {
            array_walk($params['extra_data'], function ($mn, $eid) {
                EmployeeModel::updateEmployee($eid, ['leads_max_nums' => $mn]);
            });
        }
        $db->commit();
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
        $poolInfo = self::checkPoolIsCanUpdate($poolId);
        if (empty($poolInfo)) {
            throw  new RunTimeException(['leads_pool_stop_update']);
        }
        $time = time();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //修改池子的状态
        $updateRes = LeadsPoolModel::updateRecord($poolId, ['status' => $poolStatus], false);
        if (empty($updateRes)) {
            $db->rollBack();
            throw  new RunTimeException(['update_failure']);
        }
        //修改分配规则已经指向到本池子的规则状态
        $upLevelPoolRules = LeadsPoolRuleModel::getRecords(
            [
                'target_id' => $poolId,
                'target_type' => LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_POOL,
                'status[<]' => $poolStatus,
            ],
            ['id', 'pool_id'], false);
        if (!empty($upLevelPoolRules)) {
            $upLevelPoolRulesIds = array_column($upLevelPoolRules, 'id');
            foreach ($upLevelPoolRulesIds as $ruk => $ruv) {
                $updateRuleRes = LeadsPoolRuleModel::updateRecord($ruv, ['status' => $poolStatus], false);
                if (empty($updateRuleRes)) {
                    $db->rollBack();
                    throw  new RunTimeException(['update_failure']);
                }
            }
        }
        //修改本池子的分配规则
        LeadsPoolRuleModel::batchUpdateRecord(['status' => $poolStatus], ['pool_id' => $poolId], false);
        //记录操作日志
        $opLogInsertRes = LeadsPoolOpLogModel::insertRecord([
            'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_UPDATE,
            'detail' => json_encode(['pool_id' => $poolId, 'status' => $poolStatus]),
            'create_time' => $time,
            'operator' => $operatorId,
            'track_id' => Util::makeUniqueId(),
        ], false);
        if (empty($opLogInsertRes)) {
            $db->rollBack();
            throw  new RunTimeException(['insert_failure']);
        }
        $db->commit();

        //更新分配规则缓存信息
        $delCachePoolIds = array_column($upLevelPoolRules, 'pool_id');
        $delCachePoolIds[] = $poolId;
        array_map(function ($delPoolId) use ($time) {
            CacheManager::delPoolRulesCache($delPoolId, date("Ymd", $time));
        }, $delCachePoolIds);
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
     * @param $type
     * @return array|null
     */
    public static function getPoolList($page, $count, $status = LeadsPoolModel::LEADS_POOL_STATUS_ABLE, $type = LeadsPoolModel::LEADS_POOL_TYPE_SELF_CREATE)
    {
        $list = [
            'list' => [],
            'count' => 0,
        ];
        //获取不同公池的id:分配课管不展示公池信息
        $leadsConfig = DictConstants::getSet(DictConstants::LEADS_CONFIG);
        $publicPoolId = 0;
        if ($type == LeadsPoolModel::LEADS_POOL_TYPE_SELF_CREATE) {
            $publicPoolId = $leadsConfig['assistant_public_pool_id'];
        }
        $leadPoolCount = LeadsPoolModel::getCount(
            [
                'status' => $status,
                'type' => $type,
            ]);
        if (empty($leadPoolCount)) {
            return $list;
        }
        $list['count'] = $leadPoolCount;
        $leadPoolList = LeadsPoolModel::getRecords(
            [
                "AND" => [
                    'status' => $status,
                    'OR' => [
                        'type' => $type,
                        'id' => $publicPoolId,
                    ],
                ],
                "ORDER" => ["id" => "ASC"],
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
     * @return array
     */
    private static function checkPoolIsCanUpdate($poolId)
    {
        //错误的ID和总池禁止修改:false禁止修改 true允许修改
        $poolDetail = LeadsPoolModel::getById($poolId);
        if (empty($poolDetail) || ($poolDetail['type'] == LeadsPoolModel::LEADS_POOL_TYPE_PUBLIC)) {
            return [];
        }
        return $poolDetail;
    }

    /**
     * 检测目标id是否存在
     * @param $targetIds
     * @return bool
     */
    public static function checkPoolRuleTargetIdIsExists($targetIds)
    {
        $db = MysqlDB::getDB();
        $targetData = $db->select(
            LeadsPoolRuleModel::$table,
            [
                "[>]" . LeadsPoolModel::$table => ["pool_id" => "id"],
            ],
            [
                LeadsPoolRuleModel::$table . '.target_id',
            ],
            [
                LeadsPoolModel::$table . '.type' => LeadsPoolModel::LEADS_POOL_TYPE_COURSE_MANAGE_SELF_CREATE,
                LeadsPoolRuleModel::$table . '.target_id' => $targetIds,
                LeadsPoolRuleModel::$table . '.status[!]' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_DEL,
            ]);
        if (empty($targetData) || empty(array_intersect(array_column($targetData, 'target_id'), $targetIds))) {
            return false;
        }
        return true;
    }


    /**
     * 过滤课管分配规则
     * @param $rules
     * @return mixed
     */
    private static function filterCourseManageRules($rules)
    {
        if (empty($rules)) {
            return [];
        }
        array_map(function ($arv) use (&$filterData) {
            $maxNum = (int)$arv['leads_max_nums'];
            if ($maxNum <= 0) {
                throw  new RunTimeException(['cm_pool_rule_max_num_invalid']);
            }
            $filterData[$arv['target_id']] = $maxNum;
        }, $rules);
        return $filterData;
    }

    /**
     * 新增线索分配池：课管分配学员
     * @param $params
     * @param $operatorId
     * @return bool
     * @throws RunTimeException
     */
    public static function addCourseManagePool($params, $operatorId)
    {
        //检测规则是否重复存在
        $targetIds = array_column($params['alloc_rules'], 'target_id');
        $existsRes = self::checkPoolRuleTargetIdIsExists($targetIds);
        if ($existsRes) {
            throw  new RunTimeException(['pool_rule_target_id_repeat']);
        }
        $params['extra_data'] = self::filterCourseManageRules($params['alloc_rules']);
        //添加分配池和规则
        $poolId = self::add($params, $operatorId, LeadsPoolModel::LEADS_POOL_TYPE_COURSE_MANAGE_SELF_CREATE);
        //创建总池子的分配规则
        $publicPoolId = DictConstants::getSet(DictConstants::LEADS_CONFIG)['course_manage_public_pool_id'];
        LeadsPoolRuleModel::insertRecord([
            'weight' => LeadsPoolRuleModel::DEFAULT_WEIGHT,
            'target_type' => LeadsPoolModel::LEADS_POOL_TARGET_TYPE_TO_POOL,
            'target_id' => $poolId,
            'operator' => $operatorId,
            'create_time' => time(),
            'status' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_ABLE,
            'pool_id' => $publicPoolId
        ], false);
        //更新分配规则缓存信息
        CacheManager::delPoolRulesCache($publicPoolId, date("Ymd"));
        return $poolId;
    }

    /**
     * 修改线索分配池：课管分配学员
     * @param $params
     * @param $operatorId
     * @return int
     * @throws RunTimeException
     */
    public static function updateCourseManagePool($params, $operatorId)
    {

        $filterRules = [];
        if (!empty($params['alloc_rules']['add'])) {
            //检测规则是否重复存在
            $targetIds = array_column($params['alloc_rules']['add'], 'target_id');
            $existsRes = self::checkPoolRuleTargetIdIsExists($targetIds);
            if ($existsRes) {
                throw  new RunTimeException(['pool_rule_target_id_repeat']);
            }
            $filterRules = array_merge($params['alloc_rules']['add'], $filterRules);
        }
        if (!empty($params['alloc_rules']['update'])) {
            $filterRules = array_merge($params['alloc_rules']['update'], $filterRules);
        }
        $params['extra_data'] = self::filterCourseManageRules($filterRules);
        return self::update($params, $operatorId);
    }

    /**
     * 线索分配池详情：课管分配学员
     * @param $poolId
     * @return mixed
     * @throws RunTimeException
     */
    public static function courseManagePoolDetail($poolId)
    {
        //获取详情
        $detailData = self::detail($poolId)[0];
        if (empty($detailData)) {
            throw  new RunTimeException(['pool_id_is_invalid']);
        }
        //获取课管例子分配数据
        $courseManageIdList = implode(',', array_column($detailData['rules'], 'target_id'));
        $courseManageList = array_column(EmployeeModel::getCourseManageStudentCount($courseManageIdList), null, 'id');
        foreach ($detailData['rules'] as $dk => &$dv) {
            $dv['leads_max_nums'] = $courseManageList[$dv['target_id']]['leads_max_nums'];
            $dv['student_nums'] = $courseManageList[$dv['target_id']]['student_nums'];
        }
        return $detailData;
    }
}