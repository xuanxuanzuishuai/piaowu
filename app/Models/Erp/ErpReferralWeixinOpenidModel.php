<?php

namespace App\Models\Erp;

class ErpReferralWeixinOpenidModel extends ErpModel
{
    public static $table = 'referral_weixin_openid';
    //关注公众号信息 1 为关注微信公众号 0为取关微信公众号',
    const SUBSCRIBE_WE_CHAT = 1;
    const UNSUBSCRIBE_WE_CHAT = 0;
}
