<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2020/7/7
 * Time: 下午6:44
 */

namespace App\Models;

class EventModel extends Model
{
    public static $table = 'erp_event';

    const STATUS_NORMAL = 1; //正常
}