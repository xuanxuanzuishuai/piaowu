<?php
/**
 * Landing页召回
 */

namespace App\Models;

class LandingRecallModel extends Model
{
    public static $table = 'landing_recall';
    // 启用状态
    const ENABLE_STATUS_OFF = 1;        // 待启用
    const ENABLE_STATUS_ON = 2;         // 启用
    const ENABLE_STATUS_DISABLE = 3;    // 已禁用
    
    const ENABLE_STATUS_MAP = [
        self::ENABLE_STATUS_OFF => '待启用',
        self::ENABLE_STATUS_ON => '已启用',
        self::ENABLE_STATUS_DISABLE => '已禁用',
    ];
    
    const TARGET_UNREGISTER = 1;   //目标人群 - 未注册人群
    const TARGET_UNPAY = 2;   //目标人群 - 注册未付费
    
    /**
     * 获取列表和总数
     * @param $params
     * @param $limit
     * @param array $order
     * @return array
     */
    public static function searchList($params, $limit, $order = [])
    {
        $where = [];
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
        $list = self::getRecords($where);
        return [$list, $total];
    }
    
    /**
     * 校验活动是否更改为启用
     * @param $target
     * @param $sendTime
     * @return mixed
     */
    public static function checkAllow($target, $sendTime)
    {
        $where = [
            'target_population' => $target,
            'send_time' => $sendTime,
            'enable_status' => self::ENABLE_STATUS_ON,
        ];
        return self::getRecord($where);
    }
}
