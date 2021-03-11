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
    // const BIND_STATUS_UNBIND = 0;
    // const BIND_STATUS_BIND = 1;
    // const BIND_STATUS_DEL_BIND = 2;
    const BIND_STATUS_PENDING = 0; // 未绑定
    const BIND_STATUS_BIND    = 1; // 已绑定
    const BIND_STATUS_UNBIND  = 2; // 已解绑

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
     * @param $firstAgentWhere
     * @param $secondAgentWhere
     * @param $agentWhere
     * @param $page
     * @param $limit
     * @return array
     */
    public static function agentRecommendUserList($agentUserWhere, $firstAgentWhere, $secondAgentWhere, $agentWhere, $page, $limit)
    {
        $db = MysqlDB::getDB();
        $baseSql = 'select query_field
                 FROM (' .
            'SELECT
                                    if(a.parent_id=0,au.agent_id,a.parent_id) AS first_agent_id,
                                    au.agent_id AS second_agent_id,
                                    au.id,au.stage,au.user_id,au.bind_time,au.deadline
                                FROM
                                    ' . self::$table . ' as au
                                    INNER JOIN ' . AgentModel::$table . ' AS a ON au.agent_id = a.id '.$agentWhere.'
                                WHERE ' . $agentUserWhere . '
                                ) as tma 
             INNER JOIN ' . AgentModel::$table . ' AS fa ON tma.first_agent_id=fa.id  ' . $firstAgentWhere .
            ' INNER JOIN ' . AgentModel::$table . ' AS sa ON tma.second_agent_id=sa.id  ' . $secondAgentWhere .
            ' LEFT JOIN ' . AgentDivideRulesModel::$table . ' AS dr ON fa.id = dr.agent_id AND dr.status = ' . AgentDivideRulesModel::STATUS_OK . ' ORDER BY tma.id DESC';
        $countSql = 'count(tma.id) as total_count';
        $listSql = 'tma.*,fa.type,fa.NAME AS "first_agent_name",
        IF( tma.first_agent_id = tma.second_agent_id, null, sa.NAME ) AS "second_agent_name",
        IF( tma.first_agent_id = tma.second_agent_id, null, tma.second_agent_id ) AS `second_agent_id_true`,
        dr.app_id';
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
                "OR"=>[
                    "AND #the first condition"=>[
                        'user_id' => $studentId,
                        'stage' => self::STAGE_TRIAL,
                        'deadline[>=]' => time()
                    ],
                    "AND #the second condition"=>[
                        'user_id' => $studentId,
                        'stage' => self::STAGE_FORMAL,
                    ],
                ],
            ],
            ['agent_id', 'id']);
    }

    /**
     * 获取学生无效的绑定关系
     * @param $studentId
     * @return mixed
     */
    public static function getInvalidBindData(int $studentId)
    {
        return self::getRecord(
            [
                'user_id' => $studentId,
                'stage' => self::STAGE_TRIAL,
                'deadline[<]' => time()
            ],
            [
                'agent_id',
                'id'
            ]);
    }
}