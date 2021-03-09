<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 12:47
 */

namespace App\Routers;

use App\Controllers\ShowMiniApp\Landing;
use App\Middleware\ShowMiniAppOpenIdMiddleware;

class ShowMiniAppRouter extends RouterBase
{
    protected $logFilename = 'operation_show_mini.log';
    protected $uriConfig = [
        '/show_miniapp/landing/play_review'   => [
            'method'  => ['get'],
            'call'    => Landing::class . ':playReview',
            'middles' => [ShowMiniAppOpenIdMiddleware::class],
        ],
        '/show_miniapp/landing/country_code'  => [
            'method' => ['get'],
            'call'   => Landing::class . ':getCountryCode'
        ],
        '/show_miniapp/landing/send_sms_code' => [
            'method' => ['get'],
            'call'   => Landing::class . ':sendSmsCode'
        ],
        '/show_miniapp/landing/register'      => [
            'method'  => ['post'],
            'call'    => Landing::class . ':register',
            'middles' => [ShowMiniAppOpenIdMiddleware::class],
        ],
        '/show_miniapp/landing/create_bill'   => [
            'method' => ['post'],
            'call'   => Landing::class . ':createBill'
        ],
        '/show_miniapp/landing/bill_status'   => [
            'method' => ['get'],
            'call'   => Landing::class . ':billStatus'
        ],

    ];
}