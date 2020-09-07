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

    //奖章类型的活动
    const MEDAL_TYPE = 8;

    //关联奖励的类型
    const MEDAL_AWARD = 4;
}