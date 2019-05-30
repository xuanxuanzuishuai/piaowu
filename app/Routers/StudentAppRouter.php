<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;
use App\Controllers\API\MUSVG;
use App\Controllers\StudentApp\App;
use App\Controllers\StudentApp\Auth;
use App\Controllers\StudentApp\Opn;
use App\Controllers\StudentApp\Play;
use App\Controllers\StudentApp\Homework;
use App\Controllers\StudentApp\Subscription;
use App\Middleware\AppApiForStudent;
use App\Middleware\MUSVGMiddleWare;
use App\Middleware\StudentAuthCheckMiddleWareForApp;
use App\Middleware\StudentResPrivilegeCheckMiddleWareForApp;


class StudentAppRouter extends RouterBase
{
    protected $logFilename = 'dss_student.log';

    protected $uriConfig = [
        '/user/auth/get_user_id' => [ // musvg访问
            'method' => ['get'],
            'call' => MUSVG::class . ':getUserId',
            'middles' => [MUSVGMiddleWare::class]
        ],

        '/student_app/auth/login' => [
            'method' => ['post'],
            'call' => Auth::class . ':login',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/auth/token_login' => [
            'method' => ['post'],
            'call' => Auth::class . ':tokenLogin',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/auth/validate_code' => [
            'method' => ['get'],
            'call' => Auth::class . ':validateCode',
            'middles' => [AppApiForStudent::class]
        ],

        // /student_app/app
        '/student_app/app/version' => [
            'method' => ['get'],
            'call' => App::class . ':version',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/app/config' => [
            'method' => ['get'],
            'call' => App::class . ':config',
            'middles' => [AppApiForStudent::class]
        ],
        '/student_app/app/feedback' => [
            'method' => ['post'],
            'call' => App::class . ':feedback',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // /student_app/sub
        '/student_app/subscription/redeem_gift_code' => [
            'method' => ['post'],
            'call' => Subscription::class . ':redeemGiftCode',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // /student_app/opn
        '/student_app/opn/categories' => [
            'method' => ['get'],
            'call' => Opn::class . ':categories',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/collections' => [
            'method' => ['get'],
            'call' => Opn::class . ':collections',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/lessons' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessons',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/lesson' => [
            'method' => ['get'],
            'call' => Opn::class . ':lesson',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/search' => [
            'method' => ['get'],
            'call' => Opn::class . ':search',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/play/save' => [
            'method' => ['get'],
            'call' => Play::class . ':save',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/end' => [
            'method' => ['post'],
            'call' => Play::class . ':end',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/play/ai_end' => [
            'method' => ['post'],
            'call' => Play::class . ':aiEnd',
            'middles' => [MUSVGMiddleWare::class]
        ],
        '/student_app/play/rank' => [
            'method' => ['get'],
            'call' => Play::class . ':rank',
            'middles' => [MUSVGMiddleWare::class]
        ],

        '/student_app/homework/record' => [
            'method' => ['get'],
            'call' => Homework::class . ':record',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/homework/list' => [
            'method' => ['get'],
            'call' => Homework::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
    ];
}