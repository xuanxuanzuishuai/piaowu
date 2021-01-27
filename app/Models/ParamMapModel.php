<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2020/12/11
 * Time: 4:38 下午
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ParamMapModel extends Model
{
    public static $table = "param_map";
    //用户类型：1智能陪练学生 2运营系统代理商
    const TYPE_STUDENT = 1;
    const TYPE_AGENT = 2;

    /**
     * 检测代理转介绍二维码对应的代理商状态
     * @param $paramId
     * @return array|null
     */
    public static function checkParamToAgentValidStatus($paramId)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT
                    a.id as "agent_id",ap.id as "agent_parent_id" 
                FROM
                    ' . self::$table . ' as p 
                    INNER JOIN ' . AgentModel::$table . ' as a ON p.user_id = a.id
                    INNER JOIN ' . AgentModel::$table . ' AS ap ON ap.id = a.parent_id 
                WHERE
                    p.id = ' . $paramId . ' 
                    AND p.type = ' . self::TYPE_AGENT . ' 
                    AND a.status = ' . AgentModel::STATUS_OK . ' 
                    AND ap.status =' . AgentModel::STATUS_OK;
        return $db->queryAll($sql)[0];
    }


    /**
     * 通过票据查询小程序转介绍二维码数据
     * @param $qrTicket
     * @return mixed
     */
    public static function getParamByQrTicket($qrTicket)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                    id,
                    app_id,
                    type,
                    user_id,
                    param_info 
                FROM
                    " . self::$table . " 
                WHERE
                    param_info ->> '$.r' = '" . $qrTicket."'";
        return $db->queryAll($sql)[0];
    }

}