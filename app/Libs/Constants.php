<?php

namespace App\Libs;

class Constants
{
    //本系统的应用id
    const SELF_APP_ID = 19;
    //智能陪练应用id
    const SMART_APP_ID = 8;
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

}