<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/12/21
 * Time: 17:25
 */

namespace App\Models;

class MessageRecordLogModel extends Model
{
    public static $table = 'message_record_log';

    const PUSH_FAIL = 0; //推送失败
    const PUSH_SUCCESS = 1; //推送成功

    const ACTIVITY_TYPE_CHECKIN = 5; // 打卡签到活动
}
