<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 3:12 PM
 */

namespace App\Models;


class ApprovalLogModel extends Model
{
    public static $table = "approval_log";

    const OP_APPROVE = 1;
    const OP_REJECT = 2;

    public static function getByBill($billId)
    {
        return self::getRecords(['bill_id' => $billId], '*', false);
    }
}