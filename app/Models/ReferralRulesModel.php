<?php
/**
 * 学生转介绍规则数据表
 */

namespace App\Models;

use App\Libs\MysqlDB;

class ReferralRulesModel extends Model
{
    public static $table = 'referral_rules';

    const TYPE_REAL_STUDENT_REFEREE = 1;    // 真人学生转介绍学生
    const TYPE_REAL_AGENT_REFEREE = 2;    // 真人合伙人转介绍学生
    const TYPE_AI_STUDENT_REFEREE = 3;    // 智能学生转介绍学生
    const TYPE_AI_AGENT_REFEREE = 4;    // 智能合伙人转介绍学生


    /**
     * 根据邀请人身份获取当前正在执行的奖励规则
     * @param $inviteStudentIdentity
     * @param $ruleType
     * @param array $packageType
     * @return array
     */
    public static function getCurrentRunRuleInfoByInviteStudentIdentity($inviteStudentIdentity, $ruleType, array $packageType = []): array
    {
        $returnData = [];
        // 小于1 说明身份不在区间内
        if ($inviteStudentIdentity < 1) {
            return $returnData;
        }
        $time = time();
        $db = MysqlDB::getDB();
        // 获取奖励规则基本信息
        $baseSql = 'SELECT id FROM ' . self::$table . ' WHERE `type`=:type AND `start_time`<=:start_time AND `end_time`>:end_time AND `status`=:status';
        $baseRuleInfo = $db->queryAll($baseSql, [
            ':type' => $ruleType,
            ':start_time' => $time,
            ':end_time' => $time,
            ':status' => OperationActivityModel::ENABLE_STATUS_ON,
        ]);
        if (empty($baseRuleInfo)) {
            return $returnData;
        }
        // 获取详细奖励规则
        $ruleSql = 'SELECT * FROM ' . ReferralRulesRewardModel::$table . ' WHERE `rule_id`=:rule_id AND `status`=:status AND invited_status & :invited_status';
        $ruleSqlWhere = [
            ':rule_id' => $baseRuleInfo[0]['id'],
            ':status' => OperationActivityModel::ENABLE_STATUS_ON,
            ':invited_status' => $inviteStudentIdentity,
        ];
        // 如果指定了产品包类型，则只搜索对应的产品包奖励规则
        if (!empty($packageType)) {
            $ruleSql .= ' AND `type` in (:package_type)';
            $ruleSqlWhere[':package_type'] = implode(',', $packageType);
        }
        $ruleList = $db->queryAll($ruleSql, $ruleSqlWhere);
        $returnData = $baseRuleInfo;
        $returnData['rule_list'] = is_array($ruleList) ? $ruleList : [];
        return $returnData;
    }

    /**
     * 添加奖励规则
     * @param $baseData
     * @param $trailRuleData
     * @param $normalRule
     * @param $operatorId
     * @return bool|int|mixed|null|string
     */
    public static function addRule($baseData, $trailRuleData, $normalRule, $operatorId)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $time = time();
        $baseData['create_time'] = $time;
        $ruleId = self::insertRecord($baseData);
        if (empty($ruleId)) {
            $db->rollBack();
            return false;
        }
        $mergeData = array_merge($normalRule, $trailRuleData);
        array_walk($mergeData, function (&$mv) use ($ruleId, $time, $operatorId) {
            $mv['rule_id'] = $ruleId;
            $mv['create_time'] = $time;
            $mv['operator_id'] = $operatorId;

        });
        $ruleRewardId = ReferralRulesRewardModel::batchInsert($mergeData);
        if (empty($ruleRewardId)) {
            $db->rollBack();
            return false;
        }
        $db->commit();
        return $ruleId;
    }


    /**
     * 编辑奖励规则
     * @param $ruleId
     * @param $baseData
     * @param $trailRuleData
     * @param $normalRule
     * @param $operatorId
     * @return bool
     */
    public static function updateRule($ruleId, $baseData, $trailRuleData, $normalRule, $operatorId)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //修改基础数据
        $time = time();
        $baseData['update_time'] = $time;
        $ruleUpdateRes = self::updateRecord($ruleId, $baseData);
        if (empty($ruleUpdateRes)) {
            $db->rollBack();
            return false;
        }
        //修改奖励规则配置数据
        $rewardUpdateRes = ReferralRulesRewardModel::batchUpdateRecord(['operator_id' => $operatorId, 'update_time' => $time, 'status' => OperationActivityModel::ENABLE_STATUS_DISABLE], ['rule_id' => $ruleId]);
        if (empty($rewardUpdateRes)) {
            $db->rollBack();
            return false;
        }
        //写入新数据
        $mergeData = array_merge($normalRule, $trailRuleData);
        array_walk($mergeData, function (&$mv) use ($ruleId, $time, $operatorId) {
            $mv['rule_id'] = $ruleId;
            $mv['create_time'] = $time;
            $mv['operator_id'] = $operatorId;

        });
        $ruleRewardId = ReferralRulesRewardModel::batchInsert($mergeData);
        if (empty($ruleRewardId)) {
            $db->rollBack();
            return false;
        }
        $db->commit();
        return true;
    }

    /**
     * 获取规则奖励配置详情
     * @param $ruleId
     * @return array
     */
    public static function detailById($ruleId)
    {
        $db = MysqlDB::getDB();
        $list = $db->select(self::$table,
            [
                '[>]' . ReferralRulesRewardModel::$table => ['id' => 'rule_id']
            ],
            [
                self::$table . '.id',
                self::$table . '.name',
                self::$table . '.start_time',
                self::$table . '.end_time',
                self::$table . '.status',
                self::$table . '.remark',
                ReferralRulesRewardModel::$table . '.type',
                ReferralRulesRewardModel::$table . '.invited_status',
                ReferralRulesRewardModel::$table . '.status',
                ReferralRulesRewardModel::$table . '.reward_details',
                ReferralRulesRewardModel::$table . '.reward_condition',
                ReferralRulesRewardModel::$table . '.restrictions',
            ],
            [
                self::$table . '.id' => $ruleId,
                ReferralRulesRewardModel::$table . '.status' => [OperationActivityModel::ENABLE_STATUS_OFF, OperationActivityModel::ENABLE_STATUS_ON],
                'ORDER' => [ReferralRulesRewardModel::$table . '.id' => "ASC"]
            ]);
        return empty($list) ? [] : $list;
    }


    /**
     * 获取列表
     * @param $where
     * @param $page
     * @param $count
     * @return array
     */
    public static function list($where, $page, $count)
    {
        $data = [
            'total_count' => 0,
            'list' => [],
        ];
        $totalCount = self::getCount($where);
        if (empty($totalCount)) {
            return $data;
        }
        $data['total_count'] = $totalCount;
        $where['ORDER'] = ['id' => 'DESC'];
        $where['LIMIT'] = [($page - 1) * $count, $count];
        $data['list'] = self::getRecords($where, ['id', 'type', 'name', 'start_time', 'end_time', 'status', 'create_time', 'remark']);
        return $data;
    }

    /**
     * 复制奖励规则
     * @param $baseData
     * @param $ruleRewardData
     * @param $operatorId
     * @return bool|int|mixed|null|string
     */
    public static function copyRule($baseData, $ruleRewardData, $operatorId)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $time = time();
        $baseData['create_time'] = $time;
        $ruleId = self::insertRecord($baseData);
        if (empty($ruleId)) {
            $db->rollBack();
            return false;
        }
        array_walk($ruleRewardData, function (&$mv) use ($ruleId, $time, $operatorId) {
            $mv['rule_id'] = $ruleId;
            $mv['create_time'] = $time;
            $mv['operator_id'] = $operatorId;

        });
        $ruleRewardId = ReferralRulesRewardModel::batchInsert($ruleRewardData);
        if (empty($ruleRewardId)) {
            $db->rollBack();
            return false;
        }
        $db->commit();
        return $ruleId;
    }
}
