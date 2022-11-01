<?php
/**
 * 清晨转介绍路由
 */

namespace App\Routers;

use App\Controllers\Morning\MorningLanding;
use App\Middleware\MorningAuthMiddleWare;

class MorningRouter extends RouterBase
{
    protected $logFilename = 'operation_morning.log';
    public    $middleWares = [MorningAuthMiddleWare::class];

    protected $uriConfig = [
        // 获取地址代码（父级）
        '/morning/landing/get_by_parent_code' => ['method' => ['get'], 'call'   => MorningLanding::class . ':getByParentCode','middles'=>[]],
        // 根据地址代码获取地址信息
        '/morning/landing/get_by_code' => ['method' => ['get'], 'call' => MorningLanding::class . ':getByCode','middles'=>[]],
        // 增加收货地址信息并发货
        '/morning/landing/save_address_and_delivery' => ['method' => ['post'], 'call' => MorningLanding::class . ':saveAddressAndDelivery','middles'=>[]],
        // 获取体验卡订单信息
        '/morning/landing/trial_order_info' => ['method' => ['get'], 'call' => MorningLanding::class . ':trialOrderInfo','middles'=>[]],
        // 获取唯一码
        '/morning/landing/temporary_code' => ['method' => ['get'], 'call' => MorningLanding::class . ':temporaryCode'],
    ];
}