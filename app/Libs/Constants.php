<?php

namespace App\Libs;

class Constants
{
    //本系统的应用id
    const SELF_APP_ID = 19;
    //智能陪练应用id
    const SMART_APP_ID = 8;
    //真人陪练应用id
    const REAL_APP_ID = 1;
    //智能陪练获客小程序的busi_type
    const SMART_MINI_BUSI_TYPE = 8;
    //智能陪练服务号的busi_type
    const SMART_WX_SERVICE = 1;
    //真人陪练服务号的busi_type
    const LIFE_WX_SERVICE = 1;
    //真人转介绍小程序的busi_type
    const REAL_MINI_BUSI_TYPE = 13;

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

    // 积分账户类型 erp_dict表里的type
    const ERP_DICT_ACCOUNT_NAME_TYPE = 'student_account_app_type';
    // 积分账户类型  - 现金
    const ERP_ACCOUNT_NAME_CASH = '8_1001';
    const ERP_ACCOUNT_NAME_MAGIC = '3001';  // 魔法石；配合app_id:  1_3001=魔法石

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
    // 客户端类型，区分请求来源
    const FROM_TYPE_REAL_STUDENT_APP = 'real_student_app'; //真人app
    const FROM_TYPE_REAL_STUDENT_WX = 'real_student_wx'; //真人学生微信

    //奖励类型
    const AWARD_TYPE_TIME=1;//时长
    const AWARD_TYPE_GOLD_LEAF=2;//金叶子
    const AWARD_TYPE_MAGIC_STONE=3;//魔法石
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


    //真人业务线账户登陆类型：1: app登录 2：公众号登录 3：真人转介绍小程序 4：领课小程序 5：官网领课 6:主课H5
    const REAL_STUDENT_LOGIN_TYPE_APP = 1;
    const REAL_STUDENT_LOGIN_TYPE_WX = 2;
    const REAL_STUDENT_LOGIN_TYPE_REFERRAL_MINI = 3;
    const REAL_STUDENT_LOGIN_TYPE_TAKE_LESSON_MINI = 4;
    const REAL_STUDENT_LOGIN_TYPE_TAKE_LESSON_OFFICIAL_WEB = 5;
    const REAL_STUDENT_LOGIN_TYPE_MAIN_LESSON_H5 = 6;

}
