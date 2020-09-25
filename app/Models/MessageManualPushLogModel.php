<?php
/**
 * 手动推送消息历史记录
 * User: lizao
 * Date: 2020/9/23
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class MessageManualPushLogModel extends Model
{
    public static $table = 'message_manual_push_log';
    
}