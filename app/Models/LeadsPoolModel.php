<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

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
    //池子类型1默认总池 2助教分配学生池子 3课管分配学生池子
    const LEADS_POOL_TYPE_PUBLIC = 1;
    const LEADS_POOL_TYPE_SELF_CREATE = 2;
    const LEADS_POOL_TYPE_COURSE_MANAGE_SELF_CREATE = 3;


    /**
     * 新增线索池以及分配规则数据
     * @param $poolInsertData
     * @param $poolRulesInsertData
     * @param $operatorId
     * @return bool
     */
    public static function addLeadsPoolAndRuleData($poolInsertData, $poolRulesInsertData, $operatorId)
    {
        $time = time();
        $opLog = [];
        $trackId = Util::makeUniqueId();
        //添加线索池子
        if (!empty($poolInsertData)) {
            $insertPoolId = self::insertRecord($poolInsertData, false);
            if (empty($insertPoolId)) {
                return false;
            }
            $opLog[] = [
                'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_ADD,
                'detail' => json_encode(array_merge($poolInsertData, ['pool_id' => $insertPoolId])),
                'create_time' => $time,
                'operator' => $operatorId,
                'track_id' => $trackId,
            ];
        }
        //添加线索池子分配规则
        if (!empty($poolRulesInsertData)) {
            $batchPoolRulesInsertData = array_map(function ($prv) use ($insertPoolId, &$opLog, $time, $operatorId, $trackId) {
                $prv['pool_id'] = $insertPoolId;
                $opLog[] = [
                    'op_type' => LeadsPoolOpLogModel::OP_TYPE_POOL_RULE_ADD,
                    'detail' => json_encode($prv),
                    'create_time' => $time,
                    'operator' => $operatorId,
                    'track_id' => $trackId,
                ];
                return $prv;
            }, $poolRulesInsertData);
            $rulesBatchInsertRes = LeadsPoolRuleModel::batchInsert($batchPoolRulesInsertData, false);
            if (empty($rulesBatchInsertRes)) {
                return false;
            }
        }
        //操作日志记录
        if (!empty($opLog)) {
            $inertOpLogRes = LeadsPoolOpLogModel::batchInsert($opLog, false);
            if (empty($inertOpLogRes)) {
                return false;
            }
        }
        return $insertPoolId;
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
                    lp.`type`,
                    lpr.`id` AS `rule_id`,
                    lpr.`target_id`,
                    lpr.`status` AS `rule_status`,
                    lpr.`pool_id`,
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
        array_walk($data, function ($dv, /** @noinspection PhpUnusedParameterInspection */
                                    $dk) use (&$formatData, &$targetInfo) {
            if (!isset($formatData[$dv['id']]['detail'])) {
                $formatData[$dv['id']]['detail'] = [
                    'name' => $dv['name'],
                    'id' => $dv['id'],
                    'target_type' => $dv['target_type'],
                    'target_set_id' => $dv['target_set_id'],
                    'status' => $dv['status'],
                    'type' => $dv['type'],
                ];
            }
            if ($dv['rule_id']) {
                if (!isset($targetInfo[$dv['target_type']][$dv['target_id']])) {
                    $tmpTargetInfo = [];
                    if ($dv['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_POOL) {
                        $tmpTargetInfo = self::getById($dv['target_id']);
                    } elseif ($dv['target_type'] == self::LEADS_POOL_TARGET_TYPE_TO_PEOPLE) {
                        $tmpTargetInfo = EmployeeModel::getById($dv['target_id']);
                    }
                    $targetInfo[$dv['target_type']][$dv['target_id']] = $tmpTargetInfo;
                }
                $formatData[$dv['id']]['rules'][] = [
                    'rule_id' => $dv['rule_id'],
                    'pool_id' => $dv['pool_id'],
                    'target_id' => $dv['target_id'],
                    'status' => $dv['rule_status'],
                    'weight' => $dv['weight'],
                    'target_type' => $dv['target_type'],
                    'pool_source_name' => $dv['name'],
                    'target_name' => $targetInfo[$dv['target_type']][$dv['target_id']]['name'],
                ];
            } else {
                $formatData[$dv['id']]['rules'] = [];
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
     * @param $poolId
     * @param $opLog
     * @param $poolUpdateData
     * @param $poolRulesUpdateData
     * @param $poolRulesAddData
     * @return bool
     */
    public static function updateLeadsPoolAndRuleData($poolId, $opLog, $poolUpdateData, $poolRulesUpdateData, $poolRulesAddData)
    {
        //修改线索池子
        if (!empty($poolUpdateData)) {
            $updatePoolRes = self::updateRecord($poolId, $poolUpdateData, false);
            if (empty($updatePoolRes)) {
                return false;
            }
        }
        //修改/新增线索池子分配规则
        if (!empty($poolRulesUpdateData)) {
            foreach ($poolRulesUpdateData as $prk => $prv) {
                $rulesUpdateRes = LeadsPoolRuleModel::updateRecord($prv['rule_id'], $prv['update_data'], false);
                if (empty($rulesUpdateRes)) {
                    return false;
                }
            }
        }
        if (!empty($poolRulesAddData)) {
            $rulesAddRes = LeadsPoolRuleModel::batchInsert($poolRulesAddData, false);
            if (empty($rulesAddRes)) {
                return false;
            }
        }
        //操作日志记录
        if (!empty($opLog)) {
            $inertOpLogRes = LeadsPoolOpLogModel::batchInsert($opLog, false);
            if (empty($inertOpLogRes)) {
                return false;
            }
        }
        return true;
    }
}