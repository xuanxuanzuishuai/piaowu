<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/22
 * Time: 4:17 PM
 */

namespace App\Routers;

use App\Controllers\StudentWeb\Auth;
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

        '/student_web/referral/referrer_info' => [
            'method' => ['get'],
            'call' => Referral::class . ':referrerInfo',
            'middles' => []
        ],
    ];
}