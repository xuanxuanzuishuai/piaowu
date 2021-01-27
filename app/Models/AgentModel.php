<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class AgentModel extends Model
{
    public static $table = "agent";
    //状态：1正常 2冻结
    const STATUS_OK = 1;
    const STATUS_FREEZE = 2;

    /**
     * 新增代理账户
     * @param $agentData
     * @param $agentDivideRulesInsertData
     * @param $agentInfoInsertData
     * @return bool
     */
    public static function add($agentData, $agentDivideRulesInsertData, $agentInfoInsertData)
    {
        //记录代理商基础数据
        $agentId = self::insertRecord($agentData);
        if (empty($agentId)) {
            SimpleLogger::error('insert agent base data error', $agentData);
            return false;
        }
        //记录代理商分成规则数据
        $agentDivideRulesInsertData['agent_id'] = $agentId;
        $ruleId = AgentDivideRulesModel::insertRecord($agentDivideRulesInsertData);
        if (empty($ruleId)) {
            SimpleLogger::error('insert agent divide rule data error', $agentDivideRulesInsertData);
            return false;
        }
        //记录代理商扩展数据
        $agentInfoInsertData['agent_id'] = $agentId;
        $infoId = AgentInfoModel::insertRecord($agentInfoInsertData);
        if (empty($infoId)) {
            SimpleLogger::error('insert agent info data error', $agentInfoInsertData);
            return false;
        }
        return true;
    }

    /**
     * 编辑代理账户
     * @param $agentId
     * @param $agentUpdateData
     * @param $agentDivideRulesInsertData
     * @param $agentInfoUpdateData
     * @return bool
     */
    public static function update($agentId, $agentUpdateData, $agentDivideRulesInsertData, $agentInfoUpdateData)
    {
        //编辑代理商基础数据
        $baseUpdateRes = AgentModel::updateRecord($agentId, $agentUpdateData);
        if (empty($baseUpdateRes)) {
            SimpleLogger::error('update agent base data error', []);
            return false;
        }
        //编辑代理商分成规则数据
        $ruleUpdateRes = AgentDivideRulesModel::batchUpdateRecord(
            ['status' => AgentDivideRulesModel::STATUS_DEL],
            [
                'agent_id' => $agentId,
                'app_id' => $agentDivideRulesInsertData['app_id'],
            ]);
        if (empty($ruleUpdateRes)) {
            SimpleLogger::error('update agent divide rule data error', []);
            return false;
        }
        //记录代理商分成规则数据
        $ruleId = AgentDivideRulesModel::insertRecord($agentDivideRulesInsertData);
        if (empty($ruleId)) {
            SimpleLogger::error('insert agent divide rule data error', $agentDivideRulesInsertData);
            return false;
        }
        //编辑代理商扩展数据
        $infoId = AgentInfoModel::batchUpdateRecord($agentInfoUpdateData, ['agent_id' => $agentId]);
        if (empty($infoId)) {
            SimpleLogger::error('update agent info data error', $agentInfoUpdateData);
            return false;
        }
        return true;
    }

    /**
     * 获取代理账户详情
     * @param $agentId
     * @param $appId
     * @return array
     */
    public static function detail($agentId, $appId)
    {
        $db = MysqlDB::getDB();
        $data = $db->select(
            self::$table,
            [
                "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
                "[><]" . AgentDivideRulesModel::$table => ['id' => 'agent_id'],
                "[><]" . EmployeeModel::$table => ['employee_id' => 'id'],
                "[>]" . EmployeeModel::$table . '(EA)' => ['service_employee_id' => 'id'],

            ],
            [
                self::$table . '.id',
                self::$table . '.mobile',
                self::$table . '.service_employee_id',
                self::$table . '.employee_id',
                self::$table . '.type',
                self::$table . '.status',
                AgentInfoModel::$table . '.name',
                AgentInfoModel::$table . '.country',
                AgentInfoModel::$table . '.province',
                AgentInfoModel::$table . '.city',
                AgentInfoModel::$table . '.district',
                AgentInfoModel::$table . '.address',
                AgentInfoModel::$table . '.remark',
                AgentDivideRulesModel::$table . '.app_id',
                AgentDivideRulesModel::$table . '.type',
                AgentDivideRulesModel::$table . '.rule',
                EmployeeModel::$table . '.name(e_name)',
                EmployeeModel::$table . '.name(e_s_name)',
            ],
            [
                self::$table . '.id' => $agentId,
                AgentDivideRulesModel::$table . '.status' => AgentDivideRulesModel::STATUS_OK,
                AgentDivideRulesModel::$table . '.app_id' => $appId,
            ]
        );
        return $data[0];
    }

    /**
     * 获取代理账户列表
     * @param $where
     * @return array
     */
    public static function list($where)
    {
        $data = ['count' => 0, 'list' => [],];
        $db = MysqlDB::getDB();
        $data['count'] = $db->count(self::$table, [
            "[><]" . AgentInfoModel::$table => ['id' => 'agent_id'],
        ], [self::$table . '.id'], $where);
        if (empty($data['count'])) {
            return $data;
        }
        array_walk($where, function ($wv, $wk) use (&$whereSql) {
            $whereSql[] = ' ' . $wk . '=' . $wv . ' ';
        });
        $sql = "SELECT
                    `agent`.`id`,
                    `agent`.`mobile`,
                    `agent`.`status`,
                    `agent`.`create_time`,
                    `agent`.`employee_id`,
                    `agent`.`type`,
                    `agent`.`service_employee_id`,
                    `agent_info`.`name`,
                    `agent_info`.`country`,
                    `agent_info`.`province`,
                    `agent_info`.`city`,
                    `agent_info`.`district`,
                    `agent_info`.`address`,
                    `employee`.`name` AS `e_name`,
                    EA.`name` AS `e_s_name`,
                    count( s.student_id ) as `referral_student_count`
                FROM
                    " . self::$table . "
                    INNER JOIN " . AgentInfoModel::$table . " ON `agent`.`id` = `agent_info`.`agent_id`
                    INNER JOIN " . EmployeeModel::$table . " ON `agent`.`employee_id` = `employee`.`id`
                    LEFT JOIN " . EmployeeModel::$table . " EA ON `agent`.`service_employee_id` = `EA`.`id`
                    LEFT JOIN " . StudentInviteModel::$table . " AS s ON `agent`.`id` = s.`referee_id` 
                WHERE
                    " . implode('AND', $whereSql) . "
                GROUP BY
                    " . self::$table . ".`id` ORDER BY  " . self::$table . ".id DESC";
        $data['list'] = $db->queryAll($sql);
        return $data;
    }

    /**
     * 获取二级代理列表
     * @param $parentIds
     * @return array|null
     */
    public static function agentSecondaryData($parentIds)
    {
        $db = MysqlDB::getDB();
        $secondaryData = $db->select(
            self::$table,
            ['[><]' . AgentInfoModel::$table => ['id' => 'agent_id']],
            [
                self::$table.'.id', self::$table.'.mobile', self::$table.'.status',
                self::$table.'.parent_id', self::$table.'.create_time', self::$table.'.employee_id',
                self::$table.'.type',AgentInfoModel::$table.'.name'
            ],
            [
                self::$table.'.parent_id' => $parentIds
            ]);
        return $secondaryData;
    }
}