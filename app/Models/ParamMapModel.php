<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2020/12/11
 * Time: 4:38 下午
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\UserCenter;

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
     * 更新小程序二维码图片地址
     * @param $id
     * @param $qrUrl
     * @return array
     */
    public static function updateParamInfoQrUrl($id, $qrUrl)
    {
        $db = MysqlDB::getDB();
        $sql = "UPDATE 
                    param_map 
                    SET param_info = JSON_SET( param_info, '$.qr_url', " . $qrUrl . " ) 
                WHERE
                    id = " . $id;
        return $db->queryAll($sql);
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
                    param_info ->> '$.r' = '" . $qrTicket . "'";
        return $db->queryAll($sql)[0];
    }

    /**
     * 获取小程序二维码数据:json字段查询，每个元素必须有值，不要缺失任何一个元素
     * @param $userId
     * @param $appId
     * @param $type
     * @param array $extParams 参数说明         $paramInfo = [
     *                                              'c' => $extParams['c'] ?? 0,//渠道ID
     *                                              'a' => $extParams['a'] ?? 0,//活动ID
     *                                              'e' => $extParams['e'] ?? 0,//员工ID
     *                                              'p' => $extParams['p'] ?? 0,//海报ID：二维码智能出现在特殊指定的海报
     *                                              'lt' => $landingType,//二维码类型
     *                                         ];
     * @return array|null
     */
    public static function getQrUrl($userId, $appId, $type, array $extParams)
    {
        //组合ext查询条件
        array_walk($extParams, function ($ev, $ek) use (&$extWhere) {
            $extWhere .= " AND param_info ->>'$." . $ek . "'=" . $ev;
        });
        $db = MysqlDB::getDB();
        $sql = "SELECT
                    id,user_id,app_id,type,param_info->>'$.qr_url' as qr_url
                FROM
                    param_map 
                WHERE
                    user_id = " . $userId . " 
                    AND app_id = " . $appId . " 
                    AND type = " . $type . $extWhere;
        return $db->queryAll($sql)[0];
    }

    /**
     * 检测qr ticket对应的代理商是否有效
     * @param $qrTicket
     * @return mixed
     */
    public static function checkAgentValidStatusByQr($qrTicket)
    {
        $db = MysqlDB::getDB();
        $sql = "SELECT
                    p.id,
                    p.user_id,
                FROM
                    " . self::$table . " as p     
                INNER JOIN " . AgentModel::$table . " AS a ON a.id=p.user_id AND a.status=" . AgentModel::STATUS_OK . " 
                WHERE
                    param_info ->> '$.r' = '" . $qrTicket . "' 
                    AND app_id=" . UserCenter::AUTH_APP_ID_OP_AGENT . " 
                    AND type=" . self::TYPE_AGENT;
        return $db->queryAll($sql)[0];

    }
}