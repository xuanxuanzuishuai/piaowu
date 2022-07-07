<?php

namespace App\Routers;

use App\Controllers\Client\Activity\LimitTimeActivityController;
use App\Middleware\Client\ClientAuthMiddleware;

class ClientRouter extends RouterBase
{
    protected $logFilename = 'operation_client.log';
    public $middleWares = [ClientAuthMiddleware::class];
    public $prefix = '/client';
    protected $uriConfig = [
        //限时有奖活动路由
        '/limit_time_activity/data' => [
            'method' => ['get'],
            'call'   => LimitTimeActivityController::class . ':baseData'
        ],
    ];
}