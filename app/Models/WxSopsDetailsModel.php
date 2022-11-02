<?php

namespace App\Models;

class WxSopsDetailsModel extends Model
{
	//表名称
	public static $table = "wx_sops_details";
	//规则触发类型
	const TRIGGER_TYPE_USER_SEND_TEXT_MESSAGE        = "text";//用户发送文本消息
	const TRIGGER_TYPE_USER_SEND_IMAGE_MESSAGE       = "image";//用户发送图片消息
	const TRIGGER_TYPE_USER_SEND_VOICE_MESSAGE       = "voice";//用户发送语音消息
	const TRIGGER_TYPE_USER_SEND_VIDEO_MESSAGE       = "video";//用户发送视频消息
	const TRIGGER_TYPE_USER_SEND_SHORT_VIDEO_MESSAGE = "shortvideo";//用户发送小视频消息
	const TRIGGER_TYPE_USER_SEND_LOCATION_MESSAGE    = "location";//用户发送地理位置消息
	const TRIGGER_TYPE_USER_SEND_URL_MESSAGE         = "url";//用户发送链接消息
	const TRIGGER_TYPE_USER_CLICK_MENU               = "click";//点击自定义菜单
	const TRIGGER_TYPE_USER_SUBSCRIBE                = "subscribe";//关注公众号
	const TRIGGER_TYPE_USER_INTERACTION_ALL          = "click_and_send";//点击菜单和消息互动总和
	//不同的触发类型对应的消息发放额度以及额度有效期
	//场景			下发额度	额度有效期
	//用户发送消息	20条	48小时
	//点击自定义菜单	3条		1分钟
	//关注公众号		3条		1分钟
	//扫描二维码		3条		1分钟
	//支付成功		20条	48小时
	const TRIGGER_TYPE_VALIDITY_AND_QUANTITY = [
		self::TRIGGER_TYPE_USER_SUBSCRIBE       => ["quantity" => 3, "validity" => 60],
		self::TRIGGER_TYPE_USER_INTERACTION_ALL => ["quantity" => 20, "validity" => 172800],
	];

	//消息类型
	const MESSAGE_TYPE_TEXT        = "text";//文本
	const MESSAGE_TYPE_IMAGE       = "image";//图片
	const MESSAGE_TYPE_NEWS        = "news";//图文
	const MESSAGE_TYPE_POSTER_BASE = "poster_base";//海报底图
	const MESSAGE_TYPE_MINI_CARD   = "miniprogrampage";//小程序卡片
	const MESSAGE_TYPE_VOICE       = "voice";//语音
	const MESSAGE_TYPE_VIDEO       = "video";//视频
	const MESSAGE_TYPE_MUSIC       = "music";//音乐
	const MESSAGE_TYPE_MSGMENU     = "msgmenu";//菜单消息
	//是否检测加微1检测2不检测
	const IS_CHECK_ADD_WX_YES = 1;
	const IS_CHECK_ADD_WX_NO  = 2;
}