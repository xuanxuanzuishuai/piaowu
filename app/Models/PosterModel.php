<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/11
 * Time: 5:14 PM
 */

namespace App\Models;
class PosterModel extends Model
{
    //表名称
    public static $table = "wechat_poster";
    const STATUS_DEL = 0;//0 废除
    const STATUS_NOT_PUBLISH = 1;//1 未发布
    const STATUS_PUBLISH = 2;//2 已发布
    //图片用途类型
    const POSTER_TYPE_WECHAT_STANDARD = 1;//1微信转介绍标准海报
    const POSTER_TYPE_WECHAT_PERSONALITY = 2;//2微信转介绍自定义个性化海报
    //图片应用终端类型
    const APPLY_TYPE_STUDENT_WECHAT = UserWeixinModel::BUSI_TYPE_STUDENT_SERVER;//1学生服务号
    const APPLY_TYPE_TEACHER_WECHAT = UserWeixinModel::BUSI_TYPE_TEACHER_SERVER;//2老师服务号
    //此版本先把配置文件以配置形式写入，等版本更新在做成后台可操作配置方式
    public static $settingConfig = [
        self::APPLY_TYPE_STUDENT_WECHAT => [
            'qr_x' => 60,
            'qr_y' => 60,
            'poster_width' => 750,
            'poster_height' => 1050,
            'qr_width' => 100,
            'qr_height' => 100,
            'content1' => '跟我做，只需两步！领取现金红包！
【1】复制以下文案+海报发送至朋友圈或群聊
【2】好友付费训练营体验即可获得现金红包奖励，多邀多得上不封顶',
            'content2' => '没有明确目标，孩子不爱练琴?无法实时纠错，琴童摸不着头脑?快来试试练琴神器小叶子智能陪练!拒绝枯燥无趣，让问题不过夜，让孩子成就满满!',
        ],
        self::APPLY_TYPE_TEACHER_WECHAT => [
            'qr_x' => 60,
            'qr_y' => 60,
            'poster_width' => 750,
            'poster_height' => 1050,
            'qr_width' => 100,
            'qr_height' => 100,
            'content1' => '',
            'content2' => '',
        ]
    ];
}