<?php


namespace App\Routers;

use App\Controllers\API\Erp;
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
        '/erp/rt_activity/list' => ['method' => ['get'], 'call' => CourseManagement::class . ':rtActivityList'],
    ];

}