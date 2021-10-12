<?php


namespace App\Routers;


use App\Controllers\API\Ref;

class REFRoute extends RouterBase
{
    protected $logFilename = 'operation_referral_backend.log';
    protected $uriConfig = [
        '/ref/poster/get_qr_path' => ['method' => ['post'], 'call' => Ref::class . ':getQrPath'],
        '/ref/student/active' => ['method' => ['post'], 'call' => Ref::class . ':studentActive'],
    ];
}