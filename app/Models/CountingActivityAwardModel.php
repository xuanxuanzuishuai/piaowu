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
    //商品类型
    const TYPE_GOLD_LEAF = 1; //类型 金叶子
    const TYPE_ENTITY = 2; //类型 实物

    //金叶子发货状态
    const SHIPPING_STATUS_GOLD_LEAF_MAP = [
        1 => '未到账',
        3 => '已到账',
    ];

    //物流状态：1已揽收 2运输中 3派件中 4已签收
    const LOGISTICS_STATUS_COLLECT = 1;
    const LOGISTICS_STATUS_IN_TRANSIT = 2;
    const LOGISTICS_STATUS_IN_DISPATCH = 3;
    const LOGISTICS_STATUS_SIGN = 4;


    //发货单状态:0废除 1待发货 2已发货 3发货中 4无需发货 -1发货失败
    const SHIPPING_STATUS_DEL = 0;
    const SHIPPING_STATUS_BEFORE = 1;
    const SHIPPING_STATUS_DELIVERED = 2;
    const SHIPPING_STATUS_CENTRE = 3;
    const SHIPPING_STATUS_NO_NEED = 4;
    const SHIPPING_STATUS_FAIL = -1;


    const SHIPPING_STATUS_ENTITY_MAP = [
        self::SHIPPING_STATUS_DEL => '废除',
        self::SHIPPING_STATUS_BEFORE => '待发货',
        self::SHIPPING_STATUS_DELIVERED => '已发货',
        self::SHIPPING_STATUS_CENTRE => '发货中',
        self::SHIPPING_STATUS_NO_NEED => '无需发货',
        self::SHIPPING_STATUS_FAIL => '发货中',
    ];

    const UNIQUE_ID_PREFIX = 1001;
    const SALE_SHOP = 6;

    const REDIS_EXPRESS_KEY = 'express_details_%s';

    /**
     * 修改状态
     * @param int $id
     * @param int $shippingStatus
     * @return int|null
     */
    public static function updateStatus(int $id, int $shippingStatus = self::SHIPPING_STATUS_CENTRE)
    {
        $ret = self::updateRecord($id, ['shipping_status' => $shippingStatus]);
        if (empty($ret)) SimpleLogger::error('counting_activity_award update status error', [$id]);
        return $ret;
    }

    /**
     * 批量奖励信息
     * @param array $activityAward
     * @param int $signId
     * @return bool
     */
    public static function grantAward(array $activityAward,int $signId)
    {
        if (empty($activityAward) || empty($signId)) return false;
        $insertRow = self::batchInsert($activityAward);

        if (empty($insertRow)) {
            SimpleLogger::error('counting award config insert fail', $activityAward);
            return false;
        }

        $updateRow = CountingActivitySignModel::batchUpdateRecord([
            'award_status' => CountingActivitySignModel::AWARD_STATUS_RECEIVED,
            'award_time'   => time(),
            'update_time'  => time(),
        ], [
            'id'           => $signId,
            'award_status' => CountingActivitySignModel::AWARD_STATUS_PENDING,
        ]);
        if (empty($updateRow)) {
            SimpleLogger::error('update counting_activity_sign data error', [$signId]);
            return false;
        }

        return true;

    }
}
