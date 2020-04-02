<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/1
 * Time: 2:46 PM
 */

namespace App\Services;


use App\Models\AIBillModel;

class AIBillService
{
    /**
     * 添加订单激活记录
     * @param $billId
     * @param $uuid
     * @param $autoApply
     */
    public static function addAiBill($billId, $uuid, $autoApply)
    {
        AIBillModel::addBill([
            'bill_id' => $billId,
            'uuid' => $uuid,
            'auto_apply' => $autoApply
        ]);
    }

    /**
     * 查询订单对应的激活码是否自动激活
     * @param $billId
     * @return int
     */
    public static function autoApply($billId)
    {
        $bill = AIBillModel::getAutoApply($billId);
        if (empty($bill)) {
            return 1;
        }
        return $bill['auto_apply'];
    }
}