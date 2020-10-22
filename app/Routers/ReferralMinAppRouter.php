<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/19
 * Time: 11:40
 */
namespace App\Routers;

use App\Middleware\ReferralMinAppAuthCheckMiddleware;
use App\Middleware\ReferralMinAppDevCheckMiddleware;
use App\Controllers\ReferralMiniapp\Landing;
use App\Controllers\ReferralMiniapp\Pay;
use App\Controllers\StudentWX\Student;

class ReferralMinAppRouter extends RouterBase
{
    protected $logFilename = 'dss_referral_minapp.log';

    protected $uriConfig = [
        '/referral_miniapp/landing/send_sms_code' => [
            'method'  => ['get'],
            'call'    => Student::class.':sendSmsCode',
            'middles' => []
        ],
        '/referral_miniapp/landing/register' => [
            'method'  => ['post'],
            'call'    => Landing::class . ':register',
        ],
        '/referral_miniapp/landing/index' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':index',
            'middles' => [ReferralMinAppAuthCheckMiddleware::class],
        ],
        '/referral_miniapp/landing/notify' => [
            'method'  => ['get', 'post'],
            'call'    => Landing::class . ':notify',
            'middles' => [ReferralMinAppDevCheckMiddleware::class]
        ],
        '/referral_miniapp/landing/country_code' => [
            'method'  => ['get'],
            'call'    => Landing::class . ':getCountryCode'
        ],
        '/referral_miniapp/landing/create_bill' => [
            'method'  => ['get', 'post'],
            'call'    => Pay::class . ':createBill'
        ],
        '/referral_miniapp/landing/bill_status' => [
            'method'  => ['get'],
            'call'    => Pay::class . ':billStatus',
            'middles' => []
        ],
    ];

}