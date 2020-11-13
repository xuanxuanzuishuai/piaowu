<?php

namespace App\Routers;

use App\Controllers\SalesMaster\SalesMaster;

class SalesMasterRouter extends RouterBase
{
    protected $logFilename = 'sales_master.log';
    protected $uriConfig = [
        '/sm/customer/upload' => [
            'method' => ['post'],
            'call' => SalesMaster::class . ':dataReceived'
        ]
    ];
}