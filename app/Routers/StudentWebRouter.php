<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/22
 * Time: 4:17 PM
 */

namespace App\Routers;

use App\Controllers\StudentWeb\Auth;
use App\Controllers\StudentWeb\Pay;
use App\Controllers\StudentWeb\Referral;

class StudentWebRouter extends RouterBase
{
    protected $logFilename = 'dss_student_web.log';

    protected $uriConfig = [

        '/student_web/auth/register' => [
            'method' => ['post'],
            'call' => Auth::class . ':register',
            'middles' => []
        ],
        '/student_web/auth/validate_code' => [
            'method' => ['get'],
            'call' => Auth::class . ':validateCode',
            'middles' => []
        ],
        '/student_web/auth/login' => [
            'method' => ['post'],
            'call' => Auth::class . ':login',
            'middles' => []
        ],

        // 创建订单
        '/student_web/pay/create_bill' => [
            'method' => ['post'],
            'call' => Pay::class . ':createBill',
            'middles' => []
        ],
        // 获取订单状态
        '/student_web/pay/bill_status' => [
            'method' => ['get'],
            'call' => Pay::class . ':billStatus',
            'middles' => []
        ],

        '/student_web/referral/referrer_info' => [
            'method' => ['get'],
            'call' => Referral::class . ':referrerInfo',
            'middles' => []
        ],
    ];
}