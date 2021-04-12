<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 17:52
 */

namespace App\Models;


use App\Libs\MysqlDB;

class AgentUserModel extends Model
{
    public static $table = "agent_user";
    //绑定状态:0未绑定 1已绑定 2已解绑
    const BIND_STATUS_PENDING = 0; // 未绑定
    const BIND_STATUS_BIND = 1; // 已绑定
    const BIND_STATUS_UNBIND = 2; // 已解绑

    // 进度:0注册 1体验 2年卡
    const STAGE_REGISTER = 0;
    const STAGE_TRIAL = 1;
    const STAGE_FORMAL = 2;

    /**
     * 根据agent_id获取绑定用户列表
     * @param array $agentIdArr
     * @param array $limit [offset,limit]
     * @param array $fields
     * @return array
     */
    public static function getListByAgentId(array $agentIdArr, array $limit, array $fields = [])
    {
        $where = [
            'agent_id' => $agentIdArr,
            'stage[!]' => self::STAGE_REGISTER,
        ];
        $total = self::getCount($where);
        if ($total <= 0) {
            return [[], 0];
        }
        $where['ORDER'] = ["bind_time" => "DESC"];
        $where['LIMIT'] = $limit;
        $list = self::getRecords($where, $fields);
        return [$list, $total];
    }

    /**
     * 获取推广学员列表
     * @param $agentUserWhere
     * @param $agentWhere
     * @param $page
     * @param $limit
     * @return array
     */
    public static function agentRecommendUserList($agentUserWhere, $agentWhere, $page, $limit)
    {
        $db = MysqlDB::getDB();
        $baseSql = 'SELECT query_field
                    FROM  ' . self::$table . ' as au
                    INNER JOIN ' . AgentModel::$table . ' AS a ON au.agent_id = a.id ' . $agentWhere . '
                    WHERE ' . $agentUserWhere .
                    ' ORDER BY au.id DESC';
        $countSql = 'count(au.id) as total_count';
        $listSql = "IF
                        ( a.parent_id = 0, au.agent_id, a.parent_id ) AS first_agent_id,
                    IF
                        ( a.parent_id = 0, 0, au.agent_id ) AS second_agent_id,
                        au.id,
                        au.agent_id,
                        au.stage,
                        au.user_id,
                        au.bind_time,
                        au.deadline ";
        $dataCount = $db->queryAll(str_replace("query_field", $countSql, $baseSql));
        if (empty($dataCount[0]['total_count'])) {
            return [0, []];
        }
        $limitWhere = " LIMIT " . ($page - 1) * $limit . "," . $limit;
        $list = $db->queryAll(str_replace("query_field", $listSql, $baseSql . $limitWhere));
        return [$dataCount[0]['total_count'], $list];
    }

    /**
     * 根据agent_id获取代理的推广学员数量：绑定中或已解绑状态的所有学员
     * @param $agentIds
     * @return array|null
     */
    public static function getAgentStudentCount($agentIds)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT
                    COUNT( id ) AS s_count,
                    agent_id 
                FROM
                    ' . self::$table . ' 
                WHERE
                    agent_id IN (' . $agentIds . ')
                    and stage !=' . self::STAGE_REGISTER . ' 
                GROUP BY
                    agent_id;';
        return $db->queryAll($sql);
    }

    /**
     * 获取学生有效的绑定关系
     * @param $studentId
     * @return array
     */
    public static function getValidBindData(int $studentId)
    {
        return self::getRecord(
            [

                'user_id' => $studentId,
                'stage' => [self::STAGE_TRIAL, self::STAGE_FORMAL],
                'deadline[>=]' => time()

            ],
            ['agent_id', 'id', 'stage']);
    }

    /**
     * 获取代理和学生最新的绑定关系
     * @param $agentId
     * @param $studentId
     * @return mixed
     */
    public static function getAgentStudentLastBindData(int $agentId, int $studentId)
    {
        return self::getRecord(
            [
                'agent_id' => $agentId,
                'user_id' => $studentId,
                'stage' => [self::STAGE_TRIAL, self::STAGE_FORMAL],
                'ORDER' => ["id" => "DESC"],
                "LIMIT" => [1],
            ],
            [
                'agent_id',
                'id',
                'stage',
                'deadline',
                'bind_time',
            ]);
    }
}