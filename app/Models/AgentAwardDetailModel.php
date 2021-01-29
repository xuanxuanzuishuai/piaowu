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
        $db = MysqlDB::getDB();
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
                                    ' . self::$table . ' as ab
                                    INNER JOIN ' . AgentModel::$table . ' AS a ON ab.agent_id = a.id
                                WHERE ' . $agentBillWhere . '
                                ) as tma 
             INNER JOIN ' . AgentModel::$table . ' AS fa ON tma.first_agent_id=fa.id  ' . $firstAgentWhere .
            ' INNER JOIN ' . AgentModel::$table . ' AS sa ON tma.second_agent_id=sa.id  ' . $secondAgentWhere .
            ' LEFT JOIN ' . AgentDivideRulesModel::$table . ' AS dr ON fa.id = dr.agent_id AND dr.status = ' . AgentDivideRulesModel::STATUS_OK . ' 
            INNER JOIN `dss_dev`.' . DssGiftCodeModel::$table . ' AS gc ON tma.parent_bill_id = gc.parent_bill_id ' . $giftCodeWhere . '
            INNER JOIN `dss_dev`.' . DssErpPackageV1Model::$table . ' AS ep ON gc.bill_package_id = ep.id 
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
}