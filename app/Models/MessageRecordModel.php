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

    //消息类型：1短信 2微信
    const MSG_TYPE_SMS = 1;
    const MSG_TYPE_WEIXIN = 2;
    //活动类型：1上传截图领奖 2上传截图领返现 3基于规则手动push 4基于规则自动push
    const ACTIVITY_TYPE_AWARD = 1;
    const ACTIVITY_TYPE_CASH = 2;
    const MANUAL_PUSH = 3;
    const AUTO_PUSH = 4;
}