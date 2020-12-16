<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Models;
class WeChatConfigModel extends Model
{
    //表名称
    public static $table = "wechat_config";
    //内容类型：1文本消息 2图片消息 3模版消息
    const CONTENT_TYPE_TEXT = 1;
    const CONTENT_TYPE_IMG = 2;
    const CONTENT_TYPE_TEMPLATE = 3;

}