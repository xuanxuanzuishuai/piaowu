<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 17:52
 */

namespace App\Models;

class AgentUserModel extends Model
{
    public static $table = "agent_user";
    //绑定状态:0未绑定 1已绑定 2已解绑
    const BIND_STATUS_UNBIND = 0;
    const BIND_STATUS_BIND = 1;
    const BIND_STATUS_DEL_BIND = 2;
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
            "ORDER" => ["bind_time" => "DESC"],
            'stage[!]' => self::STAGE_REGISTER,
            "LIMIT" => $limit
        ];
        return self::getRecords($where, $fields);
    }
}