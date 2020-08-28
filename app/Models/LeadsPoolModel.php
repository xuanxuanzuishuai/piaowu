<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Models;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;

class LeadsPoolModel extends Model
{
    //表名称
    public static $table = "leads_pool";
    //状态：1启用 2禁用 3删除
    const LEADS_POOL_STATUS_ABLE = 1;
    const LEADS_POOL_STATUS_DISABLE = 2;
    const LEADS_POOL_STATUS_DEL = 3;
    //流出目标类型：1池到池 2池到人
    const LEADS_POOL_TARGET_TYPE_TO_POOL = 1;
    const LEADS_POOL_TARGET_TYPE_TO_PEOPLE = 2;
    //池子类型1默认总池 2自定义池子
    const LEADS_POOL_TYPE_PUBLIC = 1;
    const LEADS_POOL_TYPE_SELF_CREATE = 2;


    /**
     * 新增线索池以及分配规则数据
     * @param $params
     * @param $operatorId
     * @return bool
     * @throws RunTimeException
     */
    public static function addLeadsPoolAndRuleData($params, $operatorId)
    {
        $time = time();
        $opLog = [];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //添加线索池子
        $poolInsertData = [
            'name' => $params['pool_name'],
            'target_type' => $params['target_type'],
            'target_set_id' => ($params['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) ? $params['dept_id'] : 0,
            'operator' => $operatorId,
            'create_time' => $time,
            'status' => self::LEADS_POOL_STATUS_ABLE,
            'type' => self::LEADS_POOL_TYPE_SELF_CREATE,
        ];
        $opLog[] = [
            'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_ADD,
            'detail' => json_encode($poolInsertData),
            'create_time' => $time,
            'operator' => $operatorId,
        ];
        $insertResId = self::insertRecord($poolInsertData, false);
        if (empty($insertResId)) {
            $db->rollBack();
            throw  new RunTimeException(['insert_failure']);
        }
        //添加线索池子分配规则
        $poolRulesInsertData = [];
        $rulesInsertRes = false;
        if (is_array($params['alloc_rules']) && !empty($params['alloc_rules'])) {
            array_map(function ($rv) use (&$poolRulesInsertData, $insertResId, $operatorId, $time, $params, &$opLog) {
                $tmpRulesInsertData = [
                    'pool_id' => $insertResId,
                    'weight' => $rv['weight'],
                    'target_type' => $params['target_type'],
                    'target_id' => $rv['target_id'],
                    'operator' => $operatorId,
                    'create_time' => $time,
                    'status' => LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_ABLE,
                ];
                $poolRulesInsertData[] = $tmpRulesInsertData;
                $opLog[] = [
                    'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_RULE_ADD,
                    'detail' => json_encode($tmpRulesInsertData),
                    'create_time' => $time,
                    'operator' => $operatorId,
                ];
            }, $params['alloc_rules']);
            $rulesInsertRes = LeadsPoolRuleModel::batchInsert($poolRulesInsertData, false);
        }
        if (empty($rulesInsertRes)) {
            $db->rollBack();
            throw  new RunTimeException(['insert_failure']);
        }
        //操作日志记录
        LeadsPoolOpLogModel::batchInsert($opLog, false);
        $db->commit();
        return true;
    }

    /**
     * 获取线索池以及分配规则数据
     * @param $poolId
     * @return array
     */
    public static function getLeadsPoolAndRuleData($poolId)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                    lp.`id`,
                    lp.`name`,
                    lp.`target_type`,
                    lp.`target_set_id`,
                    lp.`status`,
                    lpr.`id` AS `rule_id`,
                    lpr.`target_id`,
                    lpr.`status` AS `rule_status`,
                    lpr.`weight`
                FROM " . self::$table . " as lp
                    LEFT JOIN " . LeadsPoolRuleModel::$table . " AS lpr ON lp.`id` = lpr.`pool_id`
                    AND lpr.`status` != " . LeadsPoolRuleModel::LEADS_POOL_RULE_STATUS_DEL . "
                WHERE lp.`id` = " . $poolId;
        $list = $db->queryAll($sql);
        return $list;
    }

    /**
     * 格式化池子以及对应的规则数据
     * @param $data
     * @return array
     */
    public static function formatPoolAndRuleData($data)
    {
        $formatData = $targetInfo = [];
        array_walk($data, function ($dv, $dk) use (&$formatData, &$targetInfo) {
            if (!isset($formatData[$dv['id']]['detail'])) {
                $formatData[$dv['id']]['detail'] = [
                    'name' => $dv['name'],
                    'id' => $dv['id'],
                    'target_type' => $dv['target_type'],
                    'target_set_id' => $dv['target_set_id'],
                    'status' => $dv['status'],
                ];
            }
            $formatData[$dv['id']]['rules'] = [];
            if ($dv['rule_id']) {
                if (!isset($targetInfo[$dv['target_type']][$dv['target_id']])) {
                    if ($dv['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_POOL) {
                        $targetInfo = self::getById($dv['target_id']);
                    } elseif ($dv['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) {
                        $targetInfo = EmployeeModel::getById($dv['target_id']);
                    }
                    $targetInfo[$dv['target_type']][$dv['target_id']] = $targetInfo;
                }
                $formatData[$dv['id']]['rules'][] = [
                    'rule_id' => $dv['rule_id'],
                    'target_id' => $dv['target_id'],
                    'rule_status' => $dv['rule_status'],
                    'weight' => $dv['weight'],
                    'target_type' => $dv['target_type'],
                    'pool_source_name' => $dv['name'],
                    'target_name' => $targetInfo[$dv['target_type']][$dv['target_id']]['name'],
                ];
            }

        });
        return $formatData;
    }

    /**
     * 格式化池子数据
     * @param $list
     * @return array
     */
    public static function formatPoolListData($list)
    {
        $deptInfo = [];
        $deptIds = array_unique(array_column($list, 'target_set_id'));
        if (!empty(array_unique($deptIds))) {
            $deptInfo = array_column(DeptModel::getRecords(['id' => $deptIds], ['id', 'name'], false), null, 'id');
        }
        $formatData = array_map(function ($lv) use ($deptInfo) {
            $lv["contain_members"] = '';
            if ($lv['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) {
                $lv["contain_members"] = $deptInfo[$lv['target_set_id']]['name'];
            }
            return $lv;
        }, $list);
        return $formatData;
    }

    /**
     * 修改线索池以及分配规则数据
     * @param $params
     * @param $operatorId
     * @throws RunTimeException
     */
    public static function updateLeadsPoolAndRuleData($params, $operatorId)
    {
        $time = time();
        $opLog = [];
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //修改线索池子:总池不可以修改名称和分配方式
        $updatePoolRes = $updatePoolRuleRes = true;
        if ($params['target_type'] != self::LEADS_POOL_TARGET_TYPE_TO_POOL) {
            $poolUpdateData = [
                'name' => $params['pool_name'],
                'target_type' => $params['target_type'],
                'target_set_id' => ($params['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) ? $params['dept_id'] : 0,
                'operator' => $operatorId,
            ];
            $opLog[] = [
                'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_UPDATE,
                'detail' => json_encode($poolUpdateData),
                'create_time' => $time,
                'operator' => $operatorId,
            ];
            $updatePoolRes = self::updateRecord($params['pool_id'], $poolUpdateData, false);
        }
        //修改/新增线索池子分配规则
        if (is_array($params['alloc_rules']['update']) && !empty($params['alloc_rules']['update'])) {
            array_map(function ($rv) use ($operatorId, $time, $params, $db) {
                $poolRulesUpdateData = [
                    'weight' => $rv['weight'],
                    'target_type' => $params['target_type'],
                    'target_id' => $rv['target_id'],
                    'operator' => $operatorId,
                    'status' => $rv['status'],
                ];
                $rulesUpdateRes = LeadsPoolRuleModel::updateRecord($rv['rule_id'], $poolRulesUpdateData, false);
                if (empty($rulesUpdateRes)) {
                    $db->rollBack();
                    throw  new RunTimeException(['update_failure']);
                }
            }, $params['alloc_rules']['update']);
        }
        if (is_array($params['alloc_rules']['add']) && !empty($params['alloc_rules']['add'])) {
            $poolRulesAddData = array_map(function ($rv) use ($operatorId, $time, $params, $db) {
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
            $rulesAddRes = LeadsPoolRuleModel::batchInsert($poolRulesAddData, false);
            if (empty($rulesAddRes)) {
                $db->rollBack();
                throw  new RunTimeException(['add_failure']);
            }
        }
        if (empty($updatePoolRes) && empty($updatePoolRuleRes)) {
            $db->rollBack();
            throw  new RunTimeException(['update_failure']);
        }
        $db->commit();
    }
}