<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:15
 */

namespace App\Routers;

use App\Controllers\API\Dss;
use App\Controllers\Referral\Invite;

class DSSRouter extends RouterBase
{
    protected $logFilename = 'operation_dss.log';
    protected $uriConfig = [

        '/dss/employee_activity/active_list' => ['method' => ['get'], 'call' => Dss::class . ':activeList'],
        '/dss/employee_activity/get_poster'  => ['method' => ['get'], 'call' => Dss::class . ':getPoster'],

        '/dss/referral/list' => ['method' => ['get'], 'call' => Invite::class . ':list'],
        '/dss/referral/list_and_coupon' => ['method' => ['get','post'], 'call' => Invite::class . ':listAndCoupon'],   // 转介绍学员列表，包括rt活动优惠券信息
        '/dss/referral/referral_info' => ['method' => ['get'], 'call' => Invite::class . ':referralDetail'],
        '/dss/referral/batch_referral_info' => ['method' => ['get','post'], 'call' => Invite::class . ':batchReferralDetail'],
        '/dss/referral/referee_all_user' => ['method' => ['get'], 'call' => Invite::class . ':refereeAllUser'],
        // 我邀请的学生信息列表
        '/dss/referral/my_invite_student_list' => ['method' => ['get'], 'call' => Dss::class . ':myInviteStudentList'],

        '/dss/share_post/get_params_id' => ['method' => ['post'], 'call' => Dss::class . ':getParamsId'],
        '/dss/referral/new_get_params_id' => ['method' => ['post'], 'call' => Dss::class . ':getNewParamsId'],
        '/dss/share_post/get_params_info' => ['method' => ['get'], 'call' => Dss::class . ':getParamsInfo'],
        // 上传截图奖励明细列表
        '/dss/share_post/award_list' => ['method' => ['get'], 'call' => Dss::class . ':sharePostAwardList'],
        '/dss/referral/create_relation' => ['method' => ['post'], 'call' => Dss::class . ':createRelation'],
        '/dss/red_pack/red_pack_info' => ['method' => ['get'], 'call' => Dss::class . ':redPackInfo'],
        // 海报底图数据
        '/dss/poster_base/info' => ['method' => ['get'], 'call' => Dss::class . ':posterBaseInfo'],
        // 消息信息：
        '/dss/message/info' => ['method' => ['get'], 'call' => Dss::class . ':messageInfo'],
        // 代理和订单映射关系
        '/dss/agent_bill_map/make' => ['method' => ['post'], 'call' => Dss::class . ':makeAgentBillMap'],
        // 第三方订单查询列表
        '/dss/third_part_bill/list' => ['method' => ['get'], 'call' => Dss::class . ':thirdBillList'],
        '/dss/user/logout' => ['method' => ['post'], 'call' => Dss::class . ':tokenLogout'],
        // 检测当前代理商分成模式是否可以进行社群分班
        '/dss/agent/distribution_class_condition' => ['method' => ['get'], 'call' => Dss::class . ':distributionClassCondition'],
        // 海报管理接口
        '/dss/poster/get_path_id' => ['method' => ['post'], 'call' => Dss::class . ':getPathId'],   // 不存在新增
        //是不是线下代理
        '/dss/student/is_bind_offline' => ['method' => ['get'], 'call' => Dss::class . ':isBindOffline'],

        // 获取待发放金叶子积分明细
        '/dss/integral/gold_leaf_list' => ['method' => ['get'], 'call' => Dss::class . ':goldLeafList'],
        '/dss/student/wx_menu_type' => ['method' => ['get'], 'call' => Dss::class . ':getUserMenuType'],
        '/dss/student/update_tag' => ['method' => ['get'], 'call' => Dss::class . ':updateUserTag'],
        // 积分兑换红包列表
        '/dss/points/exchange_red_pack_list' => ['method' => ['get'], 'call' => Dss::class . ':pointsExchangeRedPackList'],
        '/dss/awards/red_pack_list' => ['method' => ['get', 'post'], 'call' => Dss::class . ':awardRedPackList'],
        // dss手动重试发送积分红包
        '/dss/points/retry_exchange_red_pack' => ['method' => ['post'], 'call' => Dss::class . ':retryExchangeRedPack'],

        // SHARE_POSER:
        '/dss/share_poster/list'         => ['method' => ['get', 'post'], 'call' => Dss::class . ':posterList'],
        '/dss/share_poster/get'          => ['method' => ['get'], 'call' => Dss::class . ':getSharePoster'],
        '/dss/share_poster/upload'       => ['method' => ['post'], 'call' => Dss::class . ':uploadSharePoster'],
        '/dss/share_poster/approval'     => ['method' => ['post'], 'call' => Dss::class . ':approvalPoster'],
        '/dss/share_poster/refused'      => ['method' => ['post'], 'call' => Dss::class . ':refusedPoster'],
        '/dss/activity/list'             => ['method' => ['get'], 'call' => Dss::class . ':activityList'],
        '/dss/share_poster/parse_unique' => ['method' => ['post'], 'call' => Dss::class . ':parseUnique'],

        // 转介绍专属售卖落地页 - 好友推荐专属奖励
        '/dss/referral/awards' => ['method' => ['get'], 'call' => Dss::class . ':getAwardInfo'],

        /** rt亲友优惠券活动 */
        '/dss/rt_activity/list' => ['method' => ['get'], 'call' => Dss::class . ':rtActivityList'],
        '/dss/rt_activity/info' => ['method' => ['get'], 'call' => Dss::class . ':rtActivityInfo'],
        '/dss/rt_activity/coupon_id_list' => ['method' => ['get'], 'call' => Dss::class . ':rtActivityCouponIdList'],
        '/dss/rt_activity/coupon_user_list' => ['method' => ['get','post'], 'call' => Dss::class . ':rtActivityCouponUserList'],
        '/dss/rt_activity/get_poster'        => ['method' => ['post'], 'call' => Dss::class . ':getRtPoster'],
        '/dss/rt_activity/get_referral_nums' => ['method' => ['post'], 'call' => Dss::class . ':getReferralNums'],

        //金叶子商城
        '/dss/sale_shop/button_info' => ['method' => ['get'], 'call' => Dss::class . ':buttonInfo'],
        '/dss/sale_shop/poster_lists' => ['method' => ['get'], 'call' => Dss::class . ':posterLists'],
        '/dss/sale_shop/poster_word_lists' => ['method' => ['get'], 'call' => Dss::class . ':posterWordLists'],
        '/dss/sale_shop/user_reward_details' => ['method' => ['get'], 'call' => Dss::class . ':userRewardDetails'],
        '/dss/sale_shop/banner_info' => ['method' => ['get'], 'call' => Dss::class . ':bannerInfo'],

        //周周领奖白名单
        '/dss/week_white/create' => ['method' => ['post'], 'call' => Dss::class . ':createWeekWhiteList'],
        '/dss/week_white/list' => ['method' => ['get'], 'call' => Dss::class . ':getWeekWhiteList'],
        '/dss/week_white/del' => ['method' => ['post'], 'call' => Dss::class . ':delWeekWhite'],
        '/dss/white_record/list' => ['method' => ['get'], 'call' => Dss::class . ':getWeekWhiteRecord'],
        '/dss/white_grant/list' => ['method' => ['get'], 'call' => Dss::class . ':getWhiteGrantRecord'],
        '/dss/white_grant/update' => ['method' => ['post'], 'call' => Dss::class . ':updateGrantRecord'],
        '/dss/white_grant/manualGrant' => ['method' => ['post'], 'call' => Dss::class . ':manualGrant'],

    ];
}