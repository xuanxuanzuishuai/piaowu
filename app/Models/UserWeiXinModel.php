<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/01/26
 * Time: 5:14 PM
 */

namespace App\Models;

class UserWeiXinModel extends Model
{
    //表名称
    public static $table = "user_weixin";

    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;

    const USER_TYPE_AGENT = 4;

    const BUSI_TYPE_AGENT_MINI = 9; // 代理小程序

}