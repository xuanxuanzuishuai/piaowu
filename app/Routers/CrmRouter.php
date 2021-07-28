<?php


namespace App\Routers;

use App\Controllers\API\Crm;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class CrmRouter extends RouterBase
{
    protected $logFilename = 'operation_crm.log';
    public $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

    protected $uriConfig = [
        '/crm/referral/referee_list' => ['method' => ['get', 'post'], 'call' => Crm::class . ':refereeList'],
    ];

}