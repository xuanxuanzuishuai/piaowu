<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/7
 * Time: 下午6:45
 */

namespace App\Models;

class EventTaskModel extends Model
{
    public static $table = 'erp_event_task';

    const STATUS_NORMAL = 1; // 启用
    const STATUS_DOWN = 2; // 禁用
}