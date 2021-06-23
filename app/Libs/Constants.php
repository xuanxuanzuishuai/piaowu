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

    // 积分账户类型 erp_dict表里的type
    const ERP_DICT_ACCOUNT_NAME_TYPE = 'student_account_app_type';
    // 积分账户类型  - 现金
    const ERP_ACCOUNT_NAME_CASH = '8_1001';

    // 版权区域代码
    const DICT_COPYRIGHT_CODE_CN = 'CN'; // 大陆版权
    const DICT_COPYRIGHT_CODE_CN_GAT = 'CN:GAT'; // 港澳台版权
    const DICT_COPYRIGHT_CODE_OVERSEAS = 'OVERSEAS'; // 海外版权
}