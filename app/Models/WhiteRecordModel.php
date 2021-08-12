<?php
/**
 * 白名单操作记录
 * User: yangpeng
 * Date: 2021/8/12
 * Time: 10:35 AM
 */

namespace App\Models;


class WhiteRecordModel extends Model
{
    public static $table = "white_record";

    const TYPE_ADD = 1;
    const TYPE_DEL = 2;

    public static $types = [
        self::TYPE_ADD => '添加白名单',
        self::TYPE_DEL => '移除白名单',
    ];

}