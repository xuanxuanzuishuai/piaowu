<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/3/21
 * Time: 10:52
 */

namespace App\Models;


class AgentAwardBillExtModel extends Model
{
    public static $table = "agent_award_bill_ext";
    //撞单:1转介绍与绑定中的代理商撞单 2不撞单 3绑定中代理商与成单代理商撞单 4转介绍与成单的代理商撞单 5转介绍&&绑定中的代理商&&成单的代理商三者撞单
    const IS_HIT_ORDER_REFERRAL_HIT_BIND_AGENT = 1;
    const IS_HIT_ORDER_NO = 2;
    const IS_HIT_ORDER_AGENT_HIT_AGENT = 3;
    const IS_HIT_ORDER_REFERRAL_HIT_SIGNER_AGENT = 4;
    const IS_HIT_ORDER_REFERRAL_HIT_AND_BIND_AGENT_AND_SIGNER_AGENT = 5;


    //是否是绑定关系建立后首单:1是 2不是
    const IS_FIRST_ORDER_YES = 1;
    const IS_FIRST_ORDER_NO = 2;
    //是否是代理渠道购买:1是 2不是
    const IS_AGENT_CHANNEL_BUY_YES = 1;
    const IS_AGENT_CHANNEL_BUY_NO = 2;
    //是否有推荐人:1有 2没有
    const IS_HAVE_STUDENT_REFERRAL_YES = 1;
    const IS_HAVE_STUDENT_REFERRAL_NO = 2;
    //推广订单类型:1代理自身与下级代理推广订单综合 2代理自身直接推广订单
    const AGENT_RECOMMEND_BILL_TYPE_SELF = 1;
    const AGENT_RECOMMEND_BILL_TYPE_SELF_AND_SECOND = 2;
}