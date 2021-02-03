<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;

class AgentAwardDetailModel extends Model
{
    public static $table = "agent_award_detail";
    //状态：状态：1等待审核 2发放成功 3发放失败 4发放中 5终止发放
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAIL = 3;
    const STATUS_ING = 4;
    const STATUS_STOP = 5;
    //奖励动作类型：1购买体验卡 2购买年卡 3注册
    const AWARD_ACTION_TYPE_BUY_TRAIL_CLASS = 1;
    const AWARD_ACTION_TYPE_BUY_FORMAL_CLASS = 2;
    const AWARD_ACTION_TYPE_REGISTER = 3;


    /**
     * 推广订单列表列表
     * @param $agentBillWhere
     * @param $firstAgentWhere
     * @param $secondAgentWhere
     * @param $giftCodeWhere
     * @param $page
     * @param $limit
     * @return array
     */
    public static function agentBillsList($agentBillWhere, $firstAgentWhere, $secondAgentWhere, $giftCodeWhere, $page, $limit)
    {
        //获取从库对象
        $db = self::dbRO();
        //获取库+表完整名称
        $opAgentAwardDetailTableName = self::getTableNameWithDb();
        $opAgentTableName = AgentModel::getTableNameWithDb();
        $opAgentDivideRuleTableName = AgentDivideRulesModel::getTableNameWithDb();

        $dssPackageV1TableName = DssErpPackageV1Model::getTableNameWithDb();
        $dssGiftCodeTableName = DssGiftCodeModel::getTableNameWithDb();

        $baseSql = 'select query_field
                 FROM (' .
            'SELECT
                                    IF
                                        ( a.parent_id = 0, ab.agent_id, a.parent_id ) AS first_agent_id,
                                        ab.agent_id AS second_agent_id,
                                        ab.id,
                                        ab.ext->>\'$.parent_bill_id\' as parent_bill_id,
                                        ab.student_id
                                FROM
                                    ' . $opAgentAwardDetailTableName . ' as ab
                                    INNER JOIN ' . $opAgentTableName . ' AS a ON ab.agent_id = a.id
                                WHERE ' . $agentBillWhere . '
                                ) as tma 
             INNER JOIN ' . $opAgentTableName . ' AS fa ON tma.first_agent_id=fa.id  ' . $firstAgentWhere .
            ' INNER JOIN ' . $opAgentTableName . ' AS sa ON tma.second_agent_id=sa.id  ' . $secondAgentWhere .
            ' LEFT JOIN ' . $opAgentDivideRuleTableName . ' AS dr ON fa.id = dr.agent_id AND dr.status = ' . AgentDivideRulesModel::STATUS_OK . ' 
            INNER JOIN ' . $dssGiftCodeTableName . ' AS gc ON tma.parent_bill_id = gc.parent_bill_id ' . $giftCodeWhere . '
            INNER JOIN ' . $dssPackageV1TableName . ' AS ep ON gc.bill_package_id = ep.id 
            ORDER BY tma.id DESC';
        $countSql = 'count(tma.id) as total_count';
        $listSql = 'tma.*,
                    fa.type,
                    fa.NAME AS `first_agent_name`,
                    gc.bill_package_id,
                    gc.bill_amount,
                    gc.code_status,
                    gc.buy_time,
                    ep.NAME AS "package_name",
                    IF( tma.first_agent_id = tma.second_agent_id, NULL, sa.NAME ) AS `second_agent_name`,
                    IF( tma.first_agent_id = tma.second_agent_id, NULL, tma.second_agent_id ) AS `second_agent_id_true`,
                    dr.app_id ';
        $dataCount = $db->queryAll(str_replace("query_field", $countSql, $baseSql));
        if (empty($dataCount[0]['total_count'])) {
            return [0, []];
        }
        $limitWhere = " LIMIT " . ($page - 1) * $limit . "," . $limit;
        $list = $db->queryAll(str_replace("query_field", $listSql, $baseSql . $limitWhere));
        return [$dataCount[0]['total_count'], $list];
    }


    /**
     * 根据agent_id获取代理的推广订单数量
     * @param $agentIds
     * @return array|null
     */
    public static function getAgentBillCount($agentIds)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT
                    COUNT( id ) AS b_count,
                    agent_id 
                FROM
                    ' . self::$table . ' 
                WHERE
                    agent_id IN (' . $agentIds . ') 
                AND action_type !=' . self::AWARD_ACTION_TYPE_REGISTER . '
                GROUP BY
                    agent_id;';
        return $db->queryAll($sql);
    }

    /**
     * 根据agent_id获取代理的推广订单列表
     * @param array $agentIdArr
     * @param array $limit [offset,limit]
     * @return array
     */
    public static function getListByAgentId(array $agentIdArr, array $limit)
    {
        $db = self::dbRO();
        $agentAwardDetailTable = AgentAwardDetailModel::getTableNameWithDb();
        $erpPackageV1Table = DssErpPackageV1Model::getTableNameWithDb();
        $gitCodeTable = DssGiftCodeModel::getTableNameWithDb();
        $sql = 'SELECT ad.id,ad.agent_id,ad.student_id,dg.bill_package_id,dg.parent_bill_id,de.name as package_name,dg.bill_amount,dg.code_status,dg.buy_time,dg.create_time,dg.employee_uuid' .
            ' FROM ' . $agentAwardDetailTable . ' as ad ' .
            " JOIN " . $gitCodeTable . " as dg ON ad.ext->'$.parent_bill_id'=dg.parent_bill_id " .
            " JOIN " . $erpPackageV1Table . " as de ON de.id=dg.bill_package_id" .
            ' WHERE ad.agent_id in (' . implode(',', $agentIdArr) . ') and ad.action_type!=' . self::AWARD_ACTION_TYPE_REGISTER . ' ORDER BY dg.buy_time DESC,ad.id asc';
        $offset = $limit[0] ?? 0;
        $limitNum = $limit[1] ?? 0;
        if ($limitNum > 0) {
            $sql .= ' LIMIT ' . $offset . ',' . $limitNum;
        }
        $list = $db->queryAll($sql);
        return is_array($list) ? $list : [];
    }
}