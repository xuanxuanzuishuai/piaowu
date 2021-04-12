<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/7
 * Time: 21:52
 */


namespace App\Models;

use App\Libs\SimpleLogger;
use App\Models\Erp\ErpPackageV1Model;

class AgentSalePackageModel extends Model
{
    public static $table = "agent_sale_package";
    //状态:1正常 2删除
    const STATUS_OK = 1;
    const STATUS_DEL = 2;

    /**
     * 记录代理商售卖课包数据
     * @param $packageIds
     * @param $agentId
     * @param $appId
     * @return bool
     */
    public static function addRecord($packageIds, $agentId, $appId)
    {
        $agentPackageInsertData = [];
        foreach ($packageIds as $pk => $pv) {
            $agentPackageInsertData[] = [
                'package_id' => $pv,
                'agent_id' => $agentId,
                'app_id' => $appId,
                'create_time' => time(),
            ];
        }
        $agentPackageId = self::batchInsert($agentPackageInsertData);
        if (empty($agentPackageId)) {
            SimpleLogger::error('insert agent sale package data error', $agentPackageInsertData);
            return false;
        }
        return true;
    }

    /**
     * 获取代理商可售卖产品包数据
     * @param $agentId
     * @param $appId
     * @return array|null
     */
    public static function getPackageData($agentId, $appId)
    {
        //数据库对象
        $db = self::dbRO();
        //表名称
        $salePackageTable = self::getTableNameWithDb();
        $erpPackageV1Table = ErpPackageV1Model::getTableNameWithDb();
        $sql = "SELECT
                    sp.package_id,
                    e.name as package_name,
                    e.thumbs ->> '$.thumbs[0]' as cover  
                FROM
                    " . $salePackageTable . " AS sp
                    INNER JOIN " . $erpPackageV1Table . " AS e ON sp.package_id = e.id 
                WHERE
                    sp.`agent_id` = " . $agentId . " 
                    AND sp.`app_id` = " . $appId . " 
                    AND sp.`status` = " . self::STATUS_OK . " 
                ORDER BY
                    sp.id DESC;";
        return $db->queryAll($sql);
    }
}