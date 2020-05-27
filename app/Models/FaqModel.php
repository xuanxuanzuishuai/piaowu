<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/05/26
 * Time: 6:14 PM
 */

namespace App\Models;


class FaqModel extends Model
{
    //表名称
    public static $table = "faq";
    //开放状态: 0禁用 1启用
    const FAQ_STATUS_DISABLE = 0;
    const FAQ_STATUS_ABLE = 1;
}