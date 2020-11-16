<?php

namespace App\Routers;

use App\Controllers\SalesMaster\SalesMaster;
use App\Middleware\SalesMasterMiddleware;


class SalesMasterRouter extends RouterBase
{
    public $middleWares = [SalesMasterMiddleware::class];
    protected $logFilename = 'sales_master.log';
    protected $uriConfig = [
        '/sm/customer/upload' => [
            'method' => ['post'],
            'call' => SalesMaster::class . ':dataReceived'
        ]
    ];
}