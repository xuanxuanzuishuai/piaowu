<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 6:56 PM
 */

namespace App\Models;


class ReferralActivityModel extends Model
{
    public static $table = 'referral_activity';
    //活动状态 0未启用 1启用 2下架
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;
    const STATUS_DOWN = 2;

    //海报的位置/大小信息
    public static $studentWXActivityPosterConfig = [
        'qr_x' => 533,
        'qr_y' => 92,
        'poster_width' => 750,
        'poster_height' => 1334,
        'qr_width' => 154,
        'qr_height' => 154
    ];
}