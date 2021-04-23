<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/19
 * Time: 11:40
 */
namespace App\Routers;

use App\Controllers\Agent\Agent;
use App\Controllers\ReferralMiniapp\Landing;
use App\Controllers\ReferralMiniapp\Pay;
use App\Controllers\StudentWX\Student;
use App\Middleware\ReferralMinAppAuthCheckMiddleware;

class ReferralMinAppRouter extends RouterBase
{
    protected $logFilename = 'operation_referral_miniapp.log';

    protected $uriConfig = [
        '/referral_miniapp/landing/send_sms_code' => [
            'method'  => ['get'],
            'call'    => Student::class.':sendSmsCode',
            'middles' => []
        ],
        '/referral_miniapp/landing/country_code' => [
            'method'  => ['get'],
            'call'    => Agent::class . ':countryCode'
        ],
         '/referral_miniapp/landing/register' => [
             'method'  => ['post'],
             'call'    => Landing::class . ':register',
             'middles' => [ReferralMinAppAuthCheckMiddleware::class],
         ],
         '/referral_miniapp/landing/index' => [
             'method'  => ['get'],
             'call'    => Landing::class . ':index',
             'middles' => [ReferralMinAppAuthCheckMiddleware::class],
         ],
        '/referral_miniapp/landing/create_bill' => [
            'method'  => ['get', 'post'],
            'call'    => Pay::class . ':createBill'
        ],
        '/referral_miniapp/landing/buy_name' => [
            'method'  => ['get', 'post'],
            'call'    => Landing::class . ':buyName',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class]
        ],
        '/referral_miniapp/landing/buy_poster' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':buyPageReferralPoster',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
        '/referral_miniapp/landing/play_review' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':playReview',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],


    ];

}