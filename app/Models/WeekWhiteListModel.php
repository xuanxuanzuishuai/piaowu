<?php
/**
 * 白名单列表
 * User: yangpeng
 * Date: 2021/8/12
 * Time: 10:35 AM
 */

namespace App\Models;


class WeekWhiteListModel extends Model
{
    public static $table = "week_white_list";
    const NORMAL_STATUS = 1; //启用
    const DISABLE_STATUS = 2; //禁用


}