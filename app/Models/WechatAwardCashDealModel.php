<?php


namespace App\Models;



class WechatAwardCashDealModel extends Model
{
    public static $table = "wechat_award_cash_deal";

    const NOT_GIVE = 1; //不发放
    const WAIT_GIVE = 2 ;//待发放
    const WAIT_RECEIVE = 3; //发放中待领取
    const GIVE_SUCCESS = 4; //发放成功
    const GIVE_FALSE = 5; //发放失败

}