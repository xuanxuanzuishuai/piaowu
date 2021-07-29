<?php


namespace App\Routers;

use App\Controllers\API\Erp;
use App\Controllers\Referral\Invite;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class ErpRouter extends RouterBase
{
    protected $logFilename = 'operation_erp.log';
    public $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

    protected $uriConfig = [
        // 兑换红包
        '/erp/integral/exchange_red_pack' => ['method' => ['post'], 'call' => Erp::class . ':integralExchangeRedPack'],
        // 获取待发放金叶子积分明细
        '/erp/integral/gold_leaf_list' => ['method' => ['get'], 'call' => Erp::class . ':goldLeafList'],

        /** rt亲友优惠券活动 */
        '/erp/rt_activity/list' => ['method' => ['get'], 'call' => Erp::class . ':rtActivityList'],


        '/erp/referral/list_and_coupon' => ['method' => ['get'], 'call' => Invite::class . ':listAndCoupon'],   // 转介绍学员列表，包括rt活动优惠券信息

        '/erp/rt_activity/get_poster' => ['method' => ['post'], 'call' => Erp::class . ':getRtPoster'],

        '/erp/rt_activity/get_activity_lists'  => ['method' => ['get'], 'call' => Erp::class . ':getActivityLists'],
        '/erp/rt_activity/get_activity_scheme' => ['method' => ['post'], 'call' => Erp::class . ':getActivityScheme'],
    ];

}