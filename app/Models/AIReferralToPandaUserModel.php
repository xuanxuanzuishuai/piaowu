<?php

namespace App\Models;


class AIReferralToPandaUserModel extends Model
{
    static $table = 'ai_referral_to_panda_user';

    const USER_TYPE_LT4D = 1; // 开班之日起7天内总有效练习天数小于4天
    const USER_TYPE_LT8D = 2; // 开班期内有效练习天数小于8天

    const USER_UNKNOWN_SUBSCRIBE = 0;  // 未关注
    const USER_IS_SUBSCRIBE = 1;       // 已关注

    const USER_NOT_SEND = 0; // 未发送
    const USER_IS_SEND = 1;  // 已发送

    const USER_SEND_SUCCESS = 1; // 发送成功
    const USER_SEND_FAILED = 2;  // 发送失败
}
