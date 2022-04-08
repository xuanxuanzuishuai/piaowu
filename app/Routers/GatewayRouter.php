<?php


namespace App\Routers;


use App\Controllers\API\Gateway;

class GatewayRouter extends RouterBase
{
    protected $logFilename = 'operation_gateway.log';

    protected $uriConfig = [
        '/gateway/auth/get_uuid' => ['method' => ['get'], 'call' => Gateway::class . ':getUuid'],
        '/gateway/auth/get_token' => ['method' => ['get'], 'call' => Gateway::class . ':getToken'],
    ];

}