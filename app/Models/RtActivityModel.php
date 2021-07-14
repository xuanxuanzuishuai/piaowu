<?php
/**
 * rt_activity 表
 */

namespace App\Models;


use App\Libs\Util;

class RtActivityModel extends Model
{
    public static $table = 'rt_activity';
    
    const ACTIVITY_RULE_TYPE_SHEQUN = 1;   // 社群活动
    const ACTIVITY_RULE_TYPE_KEGUAN = 2;   // 课管活动
    
    /**
     * 获取活动列表
     * @param $params
     * @param $limit
     * @param array $order
     * @param array $fields
     * @return array
     */
    public static function searchList($params, $limit, $order = [], $fields = [])
    {
        $where = [];
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (!empty($params['enable_status'])) {
            $where['enable_status'] = $params['enable_status'];
        }
        if (!empty($params['start_time_s'])) {
            $where['start_time[>=]'] = $params['start_time_s'];
        }
        if (!empty($params['start_time_e'])) {
            $where['start_time[<=]'] = $params['start_time_e'];
        }
        if (isset($params['rule_type']) && !Util::emptyExceptZero($params['rule_type'])) {
            $where['rule_type'] = $params['rule_type'];
        }
        if (isset($params['activity_id']) && !Util::emptyExceptZero($params['activity_id'])) {
            $where['activity_id'] = $params['activity_id'];
        }

        $total = self::getCount($where);
        if ($total <= 0) {
            return [[], 0];
        }

        // 计算是否超过总数 - 超过总数说明数据已经到最后一页
        if ($total < $limit[0]) {
            return [[], $total];
        }

        if (!empty($limit)) {
            $where['LIMIT'] = $limit;
        }
        if (empty($order)) {
            $order = ['id' => 'DESC'];
        }
        $where['ORDER'] = $order;

        $list = self::getRecords($where, $fields);
        return [$list, $total];
    }

    /**
     * 检查活动时间，与已启用活动有时间冲突，不可启用
     * @param $startTime
     * @param $endTime
     * @param $exceptActId
     * @return array
     */
    public static function checkTimeConflict($startTime, $endTime, $exceptActId = 0)
    {
        $where = [
            'start_time[<=]' => $endTime,
            'end_time[>=]' => $startTime,
            'enable_status' => OperationActivityModel::ENABLE_STATUS_ON,
        ];
        if (!empty($exceptActId)) {
            $where['id[!]'] = $exceptActId;
        }

        return self::getRecords($where);
    }
}