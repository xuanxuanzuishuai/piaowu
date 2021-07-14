<?php
/**
 * 计数任务活动黑名单列表
 *
 * User: xingkuiYu
 * Date: 2021/7/23
 * Time: 10:05 AM
 */

namespace App\Models;


class CountingActivityBlackModel extends Model
{
    public static $table = "counting_activity_black";

    const NORMAL_STATUS = 1; //有效
    const DISABLE_STATUS = 2; //无效

}