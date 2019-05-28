<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/5/28
 * Time: 下午5:31
 */

namespace App\Models;


class BillExtendModel extends Model
{
    public static $table = "bill_extend";
    const STATUS_STOP = 0;
    const STATUS_NORMAL = 1;
}