<?php


namespace App\Routers;


use App\Controllers\RealReferralMiniapp\Landing;
use App\Middleware\RealReferralMinAppAuthCheckMiddleware;

class RealReferralMinAppRouter extends RouterBase
{
    protected $logFilename = 'operation_real_referral_miniapp.log';

    protected $uriConfig = [
        '/real_referral_miniapp/landing/register' => [
            'method'  => ['post'],
            'call'    => Landing::class . ':register',
            'middles' => [RealReferralMinAppAuthCheckMiddleware::class],
        ],
        '/real_referral_miniapp/landing/index' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':index',
            'middles' => [RealReferralMinAppAuthCheckMiddleware::class],
        ],
        '/real_referral_miniapp/landing/student_status' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':getStudentStatus',
            'middles' => [RealReferralMinAppAuthCheckMiddleware::class],
        ],


    ];
}