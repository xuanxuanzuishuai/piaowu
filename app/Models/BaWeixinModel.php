<?php

namespace App\Models;


class BaWeixinModel extends Model
{
    public static $table = "ba_weixin";



    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;

    const STATUS_MSG = [
        self::STATUS_NORMAL => '正常',
        self::STATUS_DEL => '失效'
    ];
}