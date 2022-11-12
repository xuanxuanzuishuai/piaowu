<?php
namespace App\Routers;

use App\Controllers\API\OSS;

class OrgWebRouter extends RouterBase
{
    public $middleWares = [];
    protected $logFilename = 'operation_admin_web.log';
    protected $uriConfig = [

        '/api/oss/callback' => ['method' => ['post'], 'call' => OSS::class . ':callback', 'middles' => []],
        '/api/oss/signature' => ['method' => ['get'], 'call' => OSS::class . ':signature', 'middles' => []],
        ];
}
