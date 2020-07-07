<?php
namespace App\Models;


class AutoReplyAnswerModel extends Model
{
    const AUTO_REPLAY_TYPE_TEXT = 1; //微信自动回复消息类型  1文字 2图片
    const AUTO_REPLAY_TYPE_IMAGE = 2;
    //表名称
    public static $table = "wx_answer";
}