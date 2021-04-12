<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/9
 * Time: 16:52
 */

namespace App\Models;

use App\Libs\SimpleLogger;

class AgentOperationLogModel extends Model
{
    public static $table = "agent_operation_log";
    //操作类型
    const OP_TYPE_FREEZE_AGENT = 1;//1后台管理人员冻结一级代理
    const OP_TYPE_UNFREEZE_AGENT = 2;//2后台管理人员解冻一级代理
    const OP_TYPE_AGENT_FREEZE_AGENT = 3;//3上级代理冻结下级代理
    const OP_TYPE_AGENT_UNFREEZE_AGENT = 4;//4上级代理解冻下级代理
    const OP_TYPE_AGENT_DATA_UPDATE = 5;//5代理商详情数据修改

    /**
     * 根据操作类型过滤日志内容记录字段
     * @param $contents
     * @param $opType
     * @return array
     */
    private static function filterLogContent($contents, $opType)
    {
        $logContent = [];
        switch ($opType) {
            case self::OP_TYPE_FREEZE_AGENT:
            case self::OP_TYPE_UNFREEZE_AGENT:
            case self::OP_TYPE_AGENT_FREEZE_AGENT:
            case self::OP_TYPE_AGENT_UNFREEZE_AGENT:
                $logContent['contents']['status'] = $contents['status'];
                break;
            case self::OP_TYPE_AGENT_DATA_UPDATE:
                $logContent['contents'] = $contents['update_data'];
                break;
            default:
                return $logContent;
        }
        return $logContent;
    }

    /**
     * 记录日志数据
     * @param $contents
     * @param $agentId
     * @param $operatorId
     * @param $opType
     * @return bool
     */
    public static function recordOpLog($contents, $agentId, $operatorId, $opType)
    {
        $time = time();
        $opLog = self::filterLogContent($contents, $opType);
        if (empty($opLog)) {
            SimpleLogger::error("op log contents empty", ['contents' => $contents, 'operator_id' => $operatorId, 'op_type' => $opType]);
            return false;
        }
        $insertData[] = [
            'agent_id' => $agentId,
            'operator_id' => $operatorId,
            'type' => $opType,
            'content' => json_encode($opLog['contents']),
            'create_time' => $time
        ];
        return self::insertRecord($insertData);
    }
}