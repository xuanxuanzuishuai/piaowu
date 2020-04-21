<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:05 PM
 */

namespace App\Models;


class SharePosterModel extends Model
{
    public static $table = 'share_poster';
    //审核状态:1待审核 2合格 3不合格
    const STATUS_WAIT_CHECK = 1;
    const STATUS_QUALIFIED = 2;
    const STATUS_UNQUALIFIED = 3;
}