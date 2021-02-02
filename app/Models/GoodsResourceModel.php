<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;

class GoodsResourceModel extends Model
{
    public static $table = "goods_resource";

    const CONTENT_TYPE_IMAGE  = 1; // 图片
    const CONTENT_TYPE_TEXT   = 2; // 文字
    const CONTENT_TYPE_POSTER = 3; // 海报
}