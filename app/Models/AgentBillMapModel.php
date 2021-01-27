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
     * @param $paramId
     * @param $parentBillId
     * @param $studentId
     * @return bool
     */
    public static function add($paramId, $parentBillId, $studentId)
    {
        //检测二维码参数ID是否为代理类型并且代理状态是否正常
        $paramInfo = ParamMapModel::checkParamToAgentValidStatus($paramId);
        if (empty($paramInfo)) {
            SimpleLogger::error('param id relate agent status error', ['param_id' => $paramId, 'parent_bill_id' => $parentBillId, 'student_id' => $studentId]);
            return false;
        }
        $insertData = [
            'param_map_id' => $paramId,
            'bill_id' => $parentBillId,
            'student_id' => $studentId,
            'agent_id' => $paramInfo['agent_id'],
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
}