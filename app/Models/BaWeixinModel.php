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


    /**
     * 当前open_id绑定关系
     * @param $openId
     * @return mixed
     */
    public static function getOpenBoundInfo($openId)
    {
        return BaWeixinModel::getRecord(['open_id' => $openId, 'status' => self::STATUS_NORMAL]);
    }
}