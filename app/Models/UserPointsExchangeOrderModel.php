<?php


namespace App\Models;


class UserPointsExchangeOrderModel extends Model
{
    public static $table = 'user_points_exchange_order';
    const ORDER_TYPE_RED_PACK = 'red_pack';     // 积分兑换红包

    // 发放状态 - 保持和ErpEventTaskAward表发放状态保持一致，方便后期整合数据
    const STATUS_DISABLED  = 0; // 不发放
    const STATUS_WAITING   = 1; // 待发放
    const STATUS_REVIEWING = 2; // 审核中
    const STATUS_GIVE      = 3; // 发放成功
    const STATUS_GIVE_ING  = 4; // 发放中/已发放待领取
    const STATUS_GIVE_FAIL = 5; // 发放失败

    // 发放状态对应的子状态码 - 参考WeChatAwardCashDealModel
    const STATUS_CODE_RED_PACK_DATA_ERROR = 'RED_PACK_DATA_ERROR';          // 发放红包数据错误
    const STATUS_CODE_ILLEGAL_APPID	= 'ILLEGAL_APPID';  // 无效的appid
    const STATUS_CODE_NOT_BIND_WE_CHAT = 'NOT_BIND_WX'; // 用户没有绑定微信
    const STATUS_CODE_NOT_SUBSCRIBE_WE_CHAT = 'NOT_SUBSCRIBE_WE_CHAT';  // 未关注公众号
    const STATUS_CODE_ENV_SATISFY = 'NOT_SUBSCRIBE_WE_CHAT';  // 环境不对
}