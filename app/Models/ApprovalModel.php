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

    const STATUS_WAITING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_REVOKED = 3;

    const MAX_LEVELS = 3;
}