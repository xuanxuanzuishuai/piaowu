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
     * @param $packageType
     * @return array
     */
    public static function getCurrentRunRuleInfoByInviteStudentIdentity($inviteStudentIdentity, $ruleType, $packageType = []): array
    {
        $returnData = [];
        $time = time();
        $db = MysqlDB::getDB();
        // 获取奖励规则基本信息
        $baseSql = 'SELECT id FROM ' . self::$table . ' WHERE `type`=:type AND `start_time`>=:start_time AND `end_time`<=:end_time AND `status`=:status';
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
        $ruleSql = 'SELECT * FROM ' . ReferralRulesRewardModel::$table . ' WHERE `rule_id`=:rule_id AND `status`=:status AND inviter_status & :inviter_status';
        $ruleSqlWhere = [
            ':rule_id' => $baseRuleInfo[0]['id'],
            ':status' => OperationActivityModel::ENABLE_STATUS_ON,
            ':inviter_status' => $inviteStudentIdentity,
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
     * @return bool|int|mixed|null|string
     */
    public static function addRule($baseData, $trailRuleData, $normalRule)
    {
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $ruleId = self::insertRecord($baseData);
        if (empty($ruleId)) {
            $db->rollBack();
            return false;
        }
        $mergeData = array_merge($normalRule, $trailRuleData);
        array_walk($mergeData, function (&$mv) use ($ruleId) {
            $mv['rule_id'] = $ruleId;
        });
        $ruleRewardId = ReferralRulesRewardModel::batchInsert($mergeData);
        if (empty($ruleRewardId)) {
            $db->rollBack();
            return false;
        }
        $db->commit();
        return $ruleId;
    }
}
