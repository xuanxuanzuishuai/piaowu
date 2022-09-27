<?php

namespace App\Libs;

class Constants
{
	//本系统项目在git完整仓库名称
	const SELF_SYSTEM_REPO_NAME='operation_backend';
    //本系统的应用id
    const SELF_APP_ID = 19;
    //智能陪练应用id
    const SMART_APP_ID = 8;
    //真人陪练应用id
    const REAL_APP_ID = 1;
    //清晨项目应用id
    const QC_APP_ID = 26;
    // 清晨公众号
    const QC_APP_BUSI_WX_ID = 1;
    // 清晨转介绍小程序
    const QC_APP_BUSI_MINI_APP_ID = 2;
    //客户端平台类型,区分请求来源
    const FROM_TYPE_SMART_STUDENT_APP = 'smart_student_app';//smart_student_app智能学生app
    const FROM_TYPE_SMART_STUDENT_WX = 'smart_student_wx';//智能学生微信公众号
    const FROM_TYPE_SMART_STUDENT_H5 = 'smart_student_h5';//smart_student_h5智能学生h5页面
    const FROM_TYPE_REAL_STUDENT_APP = 'real_student_app'; //real_student_app真人学生app
    const FROM_TYPE_REAL_STUDENT_WX = 'real_student_wx'; //real_student_wx真人学生微信公众号
    const FROM_TYPE_REAL_STUDENT_H5 = 'real_student_h5'; //real_student_h5真人学生h5页面
    //智能陪练获客小程序的busi_type
    const SMART_MINI_BUSI_TYPE = 8;
    //智能陪练服务号的busi_type
    const SMART_WX_SERVICE = 1;
    //真人陪练服务号的busi_type
    const LIFE_WX_SERVICE = 1;
    //真人转介绍小程序的busi_type
    const REAL_MINI_BUSI_TYPE = 13;

    //操作人身份类型
    const OPERATOR_TYPE_SYSTEM = 1;//系统
    const OPERATOR_TYPE_CLIENT = 2;//客户

    //店铺定义
    const SALE_SHOP = 6;//智能店铺
    const SALE_SHOP_VIDEO_PLAY_SERVICE = 9;//真人课管服务店铺
    const SALE_SHOP_AI_REFERRAL_SERVICE = 10;//智能课管服务店铺

    // 销售商城 1 音符商城 2 金叶子商城 3 智能陪练商城 4 魔法石商城 5 真人陪练商城
    const SALE_SHOP_NOTE = 1;
    const SALE_SHOP_LEAF = 2;
    const SALE_SHOP_AI_PLAY = 3;
    const SALE_SHOP_MAGIC_STONE = 4;
    const SALE_SHOP_VIDEO_PLAY = 5;


    //用户类型定义
    const USER_TYPE_STUDENT = 1; //学生

    // 是否
    const DICT_TYPE_YES_OR_NO = 'yes_or_no_status';

    // 正常废除
    const DICT_TYPE_NORMAL_OR_INVALID = 'normal_or_invalid';

    //role id 设置
    const DICT_TYPE_ROLE_ID = 'ROLE_ID';

    const MOBILE_REGEX = "/^[0-9]{1,14}$/";

    // 系统设置
    const DICT_TYPE_SYSTEM_ENV = 'system_env';

    // DICT 分页设置  key_code
    const DEFAULT_PAGE_LIMIT = 'DEFAULT_PAGE_LIMIT';

    const STATUS_FALSE = 0;
    const STATUS_TRUE = 1;

    const UNIT_DAY = 'day';
    const UNIT_MONTH = 'month';
    const UNIT_YEAR = 'year';

    //通用的状态
    const DICT_TYPE_NORMAL_STATUS = 'normal_status';// 0废弃 1正常

    //系统外网地址
    const DICT_KEY_STATIC_FILE_URL = 'STATIC_FILE_URL';
    //分享海报截图审核原因
    const DICT_TYPE_SHARE_POSTER_CHECK_REASON = "share_poster_check_reason";
    //分享截图审核状态
    const DICT_TYPE_SHARE_POSTER_CHECK_STATUS = "share_poster_check_status";
    // JWT设置
    const DICT_KEY_JWT_ISSUER = 'JWT_ISSUER';
    const DICT_KEY_JWT_AUDIENCE = 'JWT_AUDIENCE';
    const DICT_KEY_JWT_EXPIRE = 'JWT_EXPIRE';
    const DICT_KEY_JWT_SIGNER_KEY = 'JWT_SIGNER_KEY';

    // JWT Token Type
    const DICT_KEY_TOKEN_TYPE_USER = 'TOKEN_TYPE_USER';

    //dss
    const CHANNEL_WE_CHAT_SCAN = 1226; //微信扫码注册
    // 推荐海报中二维码类型
    const DICT_TYPE_POSTER_QRCODE_TYPE = 'poster_qrcode_type';

    // DSS redis中缓存的用户微信最新活跃时间
    const DSS_OPENID_LAST_ACTIVE = 'user_last_active_time';

    //ERP nsq配置
    const DICT_KEY_NSQ_TOPIC_PREFIX = "NSQ_TOPIC_PREFIX";
    // 版权区域代码
    const DICT_COPYRIGHT_CODE_CN = 'CN'; // 大陆版权
    const DICT_COPYRIGHT_CODE_CN_GAT = 'CN:GAT'; // 港澳台版权
    const DICT_COPYRIGHT_CODE_OVERSEAS = 'OVERSEAS'; // 海外版权

    // 用户产生奖励发放节点
    const WEEK_SHARE_POSTER_AWARD_NODE = 'week_award';  // 周周领奖上传分享截图奖励节点标记
    const AWARD_NODE_TRAIL_AWARD       = 'trail_award'; // 购买体验卡奖励节点标记
    const AWARD_NODE_NORMAL_AWARD      = 'normal_award';// 购买年卡奖励节点标记
    // 奖励发送人身份
    const STUDENT_ID_INVITER = 1;   // 邀请人
    const STUDENT_ID_INVITEE = 2;  // 受邀人

    // op运营平台发货单前缀：格式共14位,前4位的1001是平台标识,后10位是系统统一生成=》10010000000000
    const UNIQUE_ID_PREFIX = 1001;
    // 发货单状态:0废除 1待发货 2已发货 3发货中 4无需发货 -1发货失败 -2取消发货 -10 因库存不足导致待发货且不发货
    const SHIPPING_STATUS_DEL = 0;
    const SHIPPING_STATUS_BEFORE = 1;
    const SHIPPING_STATUS_DELIVERED = 2;
    const SHIPPING_STATUS_CENTRE = 3;
    const SHIPPING_STATUS_NO_NEED = 4;
    const SHIPPING_STATUS_FAIL = -1;
    const SHIPPING_STATUS_CANCEL = -2;
    const SHIPPING_STATUS_SPECIAL = -10;

    //物流状态：1已揽收 2运输中 3派件中 4已签收
    const LOGISTICS_STATUS_COLLECT = 1;
    const LOGISTICS_STATUS_IN_TRANSIT = 2;
    const LOGISTICS_STATUS_IN_DISPATCH = 3;
    const LOGISTICS_STATUS_SIGN = 4;


    //erp系统积分账户类型 erp_dict表里的type
    const ERP_DICT_ACCOUNT_NAME_TYPE = 'student_account_app_type';
    //奖励类型：erp系统积分账户类型
    const ERP_ACCOUNT_NAME_CASH      = '8_1001';//现金
    const ERP_ACCOUNT_NAME_CASH_CODE = 1001;  // 学生现金账户；配合app_id:  8_1001现金
    const ERP_ACCOUNT_NAME_MAGIC     = 3001;  // 魔法石：配合app_id:  1_3001=魔法石 8_3001=音符
    const ERP_ACCOUNT_NAME_GOLD_LEFT = 3002;  // 金叶子

    //奖励类型：op系统存储使用
    const AWARD_TYPE_EMPTY       = 0;//空奖品
    const AWARD_TYPE_TIME        = 1;//智能业务线：时长
    const AWARD_TYPE_GOLD_LEAF   = 2;//智能业务线：金叶子
    const AWARD_TYPE_TYPE_NOTE   = 6;//智能业务线：音符
    const AWARD_TYPE_TYPE_ENTITY = 4;//智能业务线：实物
    const AWARD_TYPE_TYPE_LESSON = 5;//真人业务线：课程
    const AWARD_TYPE_MAGIC_STONE = 3;//真人业务线：魔法石

    // 转介绍奖励规则配置身份
    const REFERRAL_INVITER_ROOT                 = 1;    // 身份状态跟节点
    const REFERRAL_INVITER_STATUS_REGISTER      = self::REFERRAL_INVITER_ROOT; // 注册
    const REFERRAL_INVITER_STATUS_TRAIL         = self::REFERRAL_INVITER_ROOT << 1; // 体验卡
    const REFERRAL_INVITER_STATUS_TRAIL_EXPIRE  = self::REFERRAL_INVITER_ROOT << 2; // 体验卡过期未付费正式时长
    const REFERRAL_INVITER_STATUS_NORMAL        = self::REFERRAL_INVITER_ROOT << 3; // 付费正式时长未过期
    const REFERRAL_INVITER_STATUS_NORMAL_EXPIRE = self::REFERRAL_INVITER_ROOT << 4; // 正式时长已过期为续费

    //智能业务线账户登陆类型：1.app登陆 2.h5登陆  3. 公众号登陆   4评测分享小程序 5.转介绍小程序  6 召回H5
    const DSS_STUDENT_LOGIN_TYPE_APP = 1;
    const DSS_STUDENT_LOGIN_TYPE_H5 = 2;
    const DSS_STUDENT_LOGIN_TYPE_WX = 3;
    const DSS_STUDENT_LOGIN_TYPE_SHOW_MINI = 4;
    const DSS_STUDENT_LOGIN_TYPE_REFERRAL_MINI = 5;
    const DSS_STUDENT_LOGIN_TYPE_CALLBACK_H5 = 6;


    //真人业务线账户登陆类型：1: app登录 2：公众号登录 3：真人转介绍小程序 4：领课小程序 5：官网领课 6:H5 7:真人激活
    const REAL_STUDENT_LOGIN_TYPE_APP = 1;
    const REAL_STUDENT_LOGIN_TYPE_WX = 2;
    const REAL_STUDENT_LOGIN_TYPE_REFERRAL_MINI = 3;
    const REAL_STUDENT_LOGIN_TYPE_TAKE_LESSON_MINI = 4;
    const REAL_STUDENT_LOGIN_TYPE_TAKE_LESSON_OFFICIAL_WEB = 5;
    const REAL_STUDENT_LOGIN_TYPE_MAIN_LESSON_H5 = 6;
    const REAL_STUDENT_LOGIN_TYPE_REAL_LESSON_H5 = 7;

    // 课程-付费状态
    const REAL_COURSE_YES_PAY = 1;   // 付费课包
    //推荐人生成奖励时买课情况， 0不是有效用户，1仅购买了陪练课并且有剩余课时，2仅购买了正式课并且有剩余课时，3购买了陪练课和正式课并且都有剩余课时
    const REAL_REFEREE_BUY_LADDER_PLAYER            = 1;   // 仅购买了陪练课并且有剩余课时  陪练课 - 付费课
    const REAL_REFEREE_BUY_FORMAL                   = 2;   // 仅购买了正式课并且有剩余课时  主课 - 付费课
    const REAL_REFEREE_BUY_FORMAL_AND_LADDER_PLAYER = 3;   // 购买了陪练课和正式课并且都有剩余课时  陪练/主课（包含赠课）
    const REAL_REFEREE_BUY_LADDER_PLAYER_GIVE       = 4;   // 陪练课- 赠课
    const REAL_REFEREE_BUY_LADDER_PLAYER_TRAIL      = 6;   // 陪练课- 体验课
    const REAL_REFEREE_BUY_FORMAL_GIVE              = 5;   // 主课 - 赠课
    // 推荐身份对应的几种sub_type
    const REAL_REFEREE_ID_CONTRAST_SUB_TYPE = [
        // 1001: 陪练正式课 25分钟 1002: 陪练正式课 50分钟 1005:陪练正式课 60分钟
        self::REAL_REFEREE_BUY_LADDER_PLAYER       => [1001, 1002, 1005],
        // 10001：主课正式课 50分钟 10002：主课正式课25分钟
        self::REAL_REFEREE_BUY_FORMAL              => [10001, 10002],
        // 1007:赠送陪练课-25分钟, 1008:赠送陪练课-50分钟
        self::REAL_REFEREE_BUY_LADDER_PLAYER_GIVE  => [1007, 1008],
        // 1003:体验陪练课-25分钟, 1004:体验陪练课-50分钟, 1006:体验陪练课-30分钟
        self::REAL_REFEREE_BUY_LADDER_PLAYER_TRAIL => [1003, 1004, 1006],
        // 10003:主课赠课50分钟, 10004:主课赠课25分钟
        self::REAL_REFEREE_BUY_FORMAL_GIVE         => [10003, 10004],
    ];    // 推荐人购课情况对

    // 清晨学生状态
    const MORNING_STUDENT_STATUS_CANCEL        = -1;   // 注销
    const MORNING_STUDENT_STATUS_REGISTE       = 1;   // 注册
    const MORNING_STUDENT_STATUS_TRAIL         = 2;   // 体验课
    const MORNING_STUDENT_STATUS_TRAIL_EXPIRE  = 3;   // 体验课过期
    const MORNING_STUDENT_STATUS_NORMAL        = 4;   // 系统课
    const MORNING_STUDENT_STATUS_NORMAL_EXPIRE = 5;   // 系统课过期

    // 请求微信错误码
    const WX_RESPONSE_ERRCODE = 45015;  // msg: response out of time limit or subscription is canceled
}