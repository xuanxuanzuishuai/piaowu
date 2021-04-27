<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/27
 * Time: 21:52
 */


namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class BillMapModel extends Model
{
    public static $table = "bill_map";
    const USER_TYPE_AGENT = ParamMapModel::TYPE_AGENT;
    const USER_TYPE_STUDENT = ParamMapModel::TYPE_STUDENT;

    /**
     * 检测数据是否满足条件
     * @param $parentBillId
     * @param $studentId
     * @param $type
     * @return array
     */
    public static function get(string $parentBillId, int $studentId, int $type)
    {
        $mapData = self::getRecord(['student_id' => $studentId, 'bill_id' => $parentBillId, 'type' => $type], ['user_id']);
        if (empty($mapData)) {
            SimpleLogger::error('bill map data error', []);
            return [];
        }
        return $mapData;
    }


    /**
     * 获取订单映射数据
     * @param string $parentBillId
     * @param int $studentId
     * @return array
     */
    public static function paramMapDataByBillId(string $parentBillId, int $studentId)
    {
        $db = MysqlDB::getDB();
        $data = $db->queryAll("SELECT
                                pm.id,
                                pm.param_info ->> '$.r' AS qr_ticket,
                                pm.param_info ->> '$.e' AS e,
                                pm.param_info ->> '$.a' AS a,
                                pm.user_id, 
                                pm.type
                            FROM
                                " . self::$table . " AS ab
                                INNER JOIN " . ParamMapModel::$table . " AS pm ON ab.param_map_id = pm.id 
                            WHERE
                                ab.student_id = " . $studentId . " 
                                AND ab.bill_id = " . $parentBillId);
        return empty($data) ? [] : $data[0];
    }
}