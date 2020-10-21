<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/4/20
 * Time: 7:04 PM
 */

namespace App\Models;


class MessageRecordLogModel extends Model
{
    public static $table = 'message_record_log';

    const PUSH_FAIL = 0; //推送失败
    const PUSH_SUCCESS = 1; //推送成功
}