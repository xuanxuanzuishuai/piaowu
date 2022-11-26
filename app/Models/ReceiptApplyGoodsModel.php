<?php

namespace App\Models;

class ReceiptApplyGoodsModel extends Model
{
    public static $table = 'receipt_apply_goods';

    const STATUS_NORMAL = 1; //未退款
    const STATUS_REFUND = 2; //已退款
    const STATUS_DEL = 3; //删除

    const STATUS_MSG = [
        self::STATUS_NORMAL => '否',
        self::STATUS_REFUND => '是',
        self::STATUS_DEL => '删除'
    ];
}
