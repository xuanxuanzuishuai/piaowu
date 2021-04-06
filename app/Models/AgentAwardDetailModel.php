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
    //是否绑定期中订单:0否 1是 2无代理商绑定关系
    const IS_BIND_STATUS_YES = 1;
    const IS_BIND_STATUS_NO = 0;
    const IS_BIND_STATUS_NOT_HAVE_AGENT = 2;


    /**
     * 推广订单列表列表
     * @param $agentBillWhere
     * @param $giftCodeWhere
     * @param $agentWhere
     * @param $agentAwardBillExtWhere
     * @param $page
     * @param $limit
     * @return array
     */
    public static function agentBillsList($agentBillWhere, $giftCodeWhere, $agentWhere, $agentAwardBillExtWhere, $page, $limit)
    {
        //获取从库对象
        $db = self::dbRO();
        //获取库+表完整名称
        $opAgentAwardDetailTableName = self::getTableNameWithDb();
        $opAgentTableName = AgentModel::getTableNameWithDb();
        $opAgentAwardBillExtTableName = AgentAwardBillExtModel::getTableNameWithDb();
        $dssGiftCodeTableName = DssGiftCodeModel::getTableNameWithDb();

        $baseSql = 'SELECT query_field                                    
                    FROM
                        ' . $opAgentAwardDetailTableName . ' as ab
                        INNER JOIN ' . $opAgentTableName . ' AS a ON ab.agent_id = a.id ' . $agentWhere . '
                        INNER JOIN ' . $opAgentAwardBillExtTableName . ' AS bex ON ab.ext_parent_bill_id = bex.parent_bill_id ' . $agentAwardBillExtWhere . '
                        INNER JOIN ' . $dssGiftCodeTableName . ' AS gc ON ab.ext_parent_bill_id = gc.parent_bill_id ' . $giftCodeWhere . '
                    WHERE ' . $agentBillWhere . ' 
                    ORDER BY ab.id DESC';
        $countSql = 'count(ab.id) as total_count';
        $listSql = "IF
                        ( a.parent_id = 0, ab.agent_id, a.parent_id ) AS first_agent_id,
                    IF
                        ( a.parent_id = 0, 0, ab.agent_id ) AS second_agent_id,
                        ab.id,
                        ab.ext->>'$.parent_bill_id' as parent_bill_id,
                        ab.ext->>'$.division_model' as division_model,
                        ab.ext->>'$.agent_type' as agent_type,
                        ab.student_id,
                        ab.is_bind,
                        bex.signer_agent_id,
                        bex.student_referral_id";
        $dataCount = $db->queryAll(str_replace("query_field", $countSql, $baseSql));
        if (empty($dataCount[0]['total_count'])) {
            return [0, []];
        }
        $limitWhere = " LIMIT " . ($page - 1) * $limit . "," . $limit;
        $list = $db->queryAll(str_replace("query_field", $listSql, $baseSql . $limitWhere));
        return [$dataCount[0]['total_count'], $list];
    }


    /**
     * 根据agent_id获取代理的推广订单:订单归属人/成单人去重统计
     * @param $agentIds
     * @param $page
     * @param $limit
     * @param $onlyCount
     * @return array|null
     */
    public static function getAgentRecommendDuplicationBill($agentIds, $onlyCount = true, $page = 1, $limit = 20)
    {
        $data = ['count' => 0, 'list' => []];
        $db = MysqlDB::getDB();
        $baseSql = 'SELECT
                 :sql_filed
            FROM
                agent_award_bill_ext AS bex
                INNER JOIN agent_award_detail AS ad ON ad.ext_parent_bill_id = bex.parent_bill_id 
            WHERE
                (
                    bex.signer_agent_id IN (' . $agentIds . ') 
                    OR (
                        bex.own_agent_id IN (' . $agentIds . ') 
                        AND ( ( ad.ext ->> \'$.division_model\' = ' . AgentModel::DIVISION_MODEL_LEADS . '  AND bex.is_first_order = ' . AgentAwardBillExtModel::IS_FIRST_ORDER_YES . ' ) OR ( ad.ext ->> \'$.division_model\' = ' . AgentModel::DIVISION_MODEL_LEADS_AND_SALE . ' ) ) 
                        AND ad.action_type != ' . self::AWARD_ACTION_TYPE_REGISTER . '
                        AND ad.is_bind != (' . self::IS_BIND_STATUS_NO . ') 
                    ) 
                ) 
                AND ((bex.own_agent_status=' . AgentModel::STATUS_OK . ' AND bex.signer_agent_status=' . AgentModel::STATUS_OK . '))                
            ORDER BY
                bex.id DESC';
        $countSql = str_replace(":sql_filed", 'count(*) as total_count', $baseSql);
        $countData = $db->queryAll($countSql);
        if (empty($countData)) {
            return $data;
        }
        $data['count'] = $countData[0]['total_count'];
        if ($onlyCount) {
            return $data;
        }
        $limitWhere = " limit " . ($page - 1) * $limit . ',' . $limit;
        $listSql = str_replace(":sql_filed",
            'bex.id,bex.parent_bill_id,bex.student_id,bex.signer_agent_id,bex.create_time,bex.own_agent_id,bex.student_referral_id',
            $baseSql . $limitWhere);
        $data['list'] = $db->queryAll($listSql);
        return $data;
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
        $sqlFrom = ' FROM ' . $agentAwardDetailTable . ' as ad ';
        $joinGiftCode = " JOIN " . $gitCodeTable . " as dg ON ad.ext->>'$.parent_bill_id'=dg.parent_bill_id ";
        $joinErpPackage =" JOIN " . $erpPackageV1Table . " as de ON de.id=dg.bill_package_id";
        $where = ' WHERE ad.agent_id in (' . implode(',', $agentIdArr) . ') and ad.action_type!=' . self::AWARD_ACTION_TYPE_REGISTER . ' AND ad.is_bind=' . self::IS_BIND_STATUS_YES .
            ' ORDER BY dg.buy_time DESC,ad.id asc';
        $totalSql = "SELECT count(ad.id) as total ".$sqlFrom.$joinGiftCode.$joinErpPackage.$where;
        $total = $db->queryAll($totalSql);
        $total = $total[0]['total'] ?? 0;
        if ($total == 0) {
            return [[], 0];
        }
        $sql = 'SELECT ad.id,ad.agent_id,ad.student_id,dg.bill_package_id,dg.parent_bill_id,de.name as package_name,dg.bill_amount,dg.code_status,dg.buy_time,dg.create_time,dg.employee_uuid';
        $offset = $limit[0] ?? 0;
        $limitNum = $limit[1] ?? 0;
        $limitSql = "";
        if ($limitNum > 0) {
            $limitSql = ' LIMIT ' . $offset . ',' . $limitNum;
        }

        $list = $db->queryAll($sql.$sqlFrom.$joinGiftCode.$joinErpPackage.$where.$limitSql);
        return [$list,$total];
    }

    /**
     * 通过订单ID获取奖励记录
     * @param $parentBillId
     * @return array|null
     */
    public static function getDetailByParentBillId($parentBillId)
    {
        return self::getRecord(['ext_parent_bill_id' => $parentBillId], ['id']);
    }


    /**
     * 获取学生和代理商产生奖励数据的课包数量
     * @param int $agentId
     * @param int $studentId
     * @param $packageType
     * @return array|null
     */
    public static function getAgentStudentBillCountByPackageType(int $agentId, int $studentId, $packageType)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT
                    COUNT( id ) AS data_count
                FROM
                    ' . self::$table . ' 
                WHERE
                    agent_id = ' . $agentId . ' 
                AND student_id = ' . $studentId . ' 
                AND action_type !=' . self::AWARD_ACTION_TYPE_REGISTER . '
                AND ext->>\'$.package_type\' =' . $packageType;
        $data = $db->queryAll($sql);
        return $data;
    }
}