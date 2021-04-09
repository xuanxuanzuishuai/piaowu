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
        '/erp/integral/exchange_red_pack' => ['method' => ['post'], 'call' => Erp::class . ':integralExchangeRedPack'],
    ];

}