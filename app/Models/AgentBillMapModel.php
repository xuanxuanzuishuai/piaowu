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

class AgentBillMapModel extends Model
{
    public static $table = "agent_bill_map";


    /**
     * 记录数据
     * @param $qrTicket
     * @param $parentBillId
     * @param $studentId
     * @return bool
     */
    public static function add($qrTicket, $parentBillId, $studentId)
    {
        //检测二维码参数ID是否为代理类型并且代理状态是否正常
        $paramInfo = ParamMapModel::checkAgentValidStatusByQr($qrTicket);
        if (empty($paramInfo)) {
            SimpleLogger::error('qr ticket relate agent status error', ['qr_ticket' => $qrTicket, 'parent_bill_id' => $parentBillId, 'student_id' => $studentId]);
            return false;
        }
        $insertData = [
            'param_map_id' => $paramInfo['id'],
            'bill_id' => $parentBillId,
            'student_id' => $studentId,
            'agent_id' => $paramInfo['user_id'],
            'create_time' => time()
        ];
        $id = self::insertRecord($insertData);
        if (empty($id)) {
            SimpleLogger::error('insert agent bill map data error', $insertData);
            return false;
        }
        return true;
    }

    /**
     * 获取数据
     * @param $parentBillId
     * @param $studentId
     * @return array
     */
    public static function get($parentBillId, int $studentId)
    {
        $mapData = self::getRecord(['student_id' => $studentId, 'bill_id' => $parentBillId], ['agent_id']);
        if (empty($mapData)) {
            SimpleLogger::error('agent bill map data error', []);
            return [];
        }
        return $mapData;
    }

    /**
     * 根据agent_id获取代理的推广订单列表
     * @param array $agentIdArr
     * @param array $limit [offset,limit]
     * @param array $fields
     * @return array
     */
    public static function getListByAgentId(array $agentIdArr, array $limit, array $fields = [])
    {
        $where = [
            'agent_id' => $agentIdArr,
            "ORDER" => ["pay_time" => "DESC"],
            "LIMIT" => $limit
        ];
        return self::getRecords($where, $fields);
    }

    /**
     * 获取订单映射代理商数据
     * @param $parentBillId
     * @param int $studentId
     * @return array
     */
    public static function getBillMapAgentData(string $parentBillId, int $studentId)
    {
        $db = MysqlDB::getDB();
        $mapData = $db->select(self::$table,
            [
                '[><]' . AgentModel::$table => ['agent_id' => 'id']
            ],
            [
                self::$table . '.agent_id',
                self::$table . '.param_map_id',
                self::$table . '.bill_id',
                self::$table . '.student_id',
                AgentModel::$table . '.division_model',
            ],
            [
                self::$table . '.student_id' => $studentId,
                self::$table . '.bill_id' => $parentBillId,
            ]);
        return empty($mapData) ? [] : $mapData[0];
    }
}