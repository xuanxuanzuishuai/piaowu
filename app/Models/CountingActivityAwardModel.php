<?php
/**
 * 计数任务奖励学生领取记录表
 *
 * User: xingkuiYu
 * Date: 2021/7/13
 * Time: 10:35 AM
 */

namespace App\Models;

use App\Libs\SimpleLogger;

class CountingActivityAwardModel extends Model
{
    public static $table = 'counting_activity_award';


    const TYPE_GOLD_LEAF = 1; //类型 金叶子
    const TYPE_ENTITY = 2; //类型 实物

    const SHIPPING_STATUS_BACK = 1; //待发货
    const SHIPPING_STATUS_CENTRE = 2; //发货中


    /**
     * 修改状态
     * @param int $id
     * @param int $shippingStatus
     * @return int|null
     */
    public static function updateStatus(int $id, int $shippingStatus = self::SHIPPING_STATUS_CENTRE)
    {
        $ret = self::updateRecord($id, $shippingStatus);
        if (empty($ret)) SimpleLogger::error('counting_activity_award update status error', [$id]);
        return $ret;
    }
}
