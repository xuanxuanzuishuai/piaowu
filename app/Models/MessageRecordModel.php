<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 7:04 PM
 */

namespace App\Models;


class MessageRecordModel extends Model
{
    public static $table = 'message_record';

    const MSG_TYPE_SMS = 1;
    const MSG_TYPE_WEIXIN = 2;
}