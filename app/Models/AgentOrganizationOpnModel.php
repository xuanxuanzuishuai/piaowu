<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/6/10
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;

class AgentOrganizationOpnModel extends Model
{
    public static $table = "agent_organization_opn";
    //状态：1正常 2删除
    const STATUS_OK = 1;
    const STATUS_REMOVE = 2;


    /**
     * 获取机构专属曲谱教材合集
     * @param $agentId
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getOrgOpnList($agentId, $page, $limit)
    {
        $data = [
            'org_id' => 0,
            'total_count' => 0,
            'list' => []
        ];
        $orgData = AgentOrganizationModel::getRecord(['agent_id' => $agentId, 'status' => AgentOrganizationModel::STATUS_OK], ['id']);
        if (empty($orgData)) {
            return $data;
        }
        $data['org_id'] = $orgData['id'];
        $totalCount = AgentOrganizationOpnModel::getCount(['org_id' => $orgData['id'], 'status' => self::STATUS_OK]);
        if (empty($totalCount)) {
            return $data;
        }
        $data['total_count'] = $totalCount;
        $data['list'] = AgentOrganizationOpnModel::getRecords(
            [
                'org_id' => $orgData['id'],
                'status' => self::STATUS_OK,
                'LIMIT' => [($page - 1) * $limit, $limit],
                'ORDER' => ['id' => 'DESC']
            ],
            ['id(relation_id)', 'opn_id']);
        return $data;
    }

    /**
     * 获取当前代理所属的机构下关联的专属曲谱教材ID
     * @param $agentId
     * @return array
     */
    public static function getOrgOpnId($agentId)
    {
        $db = MysqlDB::getDB();
        $data = $db->select(
            AgentOrganizationModel::$table,
            [
                '[><]' . self::$table => ['id' => 'org_id'],
            ],
            self::$table . '.opn_id',
            [
                AgentOrganizationModel::$table . '.agent_id' => $agentId,
                self::$table . '.status' => self::STATUS_OK,
                'ORDER' => [self::$table . '.id' => 'DESC']
            ]);
        return empty($data) ? [] : $data;
    }
}