<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/18
 * Time: 3:10 PM
 */

namespace App\Models;


class ApprovalModel extends Model
{
    public static $table = "approval";

    const TYPE_BILL_ADD = 1;
    const TYPE_BILL_DISABLE = 2;

    const STATUS_WAITING = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_REVOKED = 4;

    const MAX_LEVELS = 3;
}