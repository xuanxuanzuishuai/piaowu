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
use App\Controllers\StudentApp\Panda;
use App\Controllers\StudentApp\Pay;
use App\Controllers\StudentApp\Play;
use App\Controllers\StudentApp\Homework;
use App\Controllers\StudentApp\Referral;
use App\Controllers\StudentApp\Subscription;
use App\Middleware\AppApiForStudent;
use App\Middleware\MUSVGMiddleWare;
use App\Middleware\StudentAuthCheckMiddleWareForApp;
use App\Middleware\StudentResPrivilegeCheckMiddleWareForApp;
use App\Controllers\StudentApp\Question;


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
        '/student_app/app/engine' => [
            'method' => ['get'],
            'call' => App::class . ':engine',
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
        '/student_app/app/action' => [
            'method' => ['post'],
            'call' => App::class . ':action',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/set_nickname' => [
            'method' => ['post'],
            'call' => App::class . ':setNickname',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/app/get_signature' => [
            'method' => ['get'],
            'call' => App::class . ':getSignature',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/app/leads_check' => [
            'method' => ['get'],
            'call' => App::class . ':leadsCheck',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        // /student_app/sub
        '/student_app/subscription/redeem_gift_code' => [
            'method' => ['post'],
            'call' => Subscription::class . ':redeemGiftCode',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/subscription/trial' => [
            'method' => ['post'],
            'call' => Subscription::class . ':trial',
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
        '/student_app/opn/lesson_limit' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessonLimit',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/lesson_resources' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessonResources',
            'middles' => [StudentResPrivilegeCheckMiddleWareForApp::class,
                StudentAuthCheckMiddleWareForApp::class,
                AppApiForStudent::class]
        ],
        '/student_app/opn/search' => [
            'method' => ['get'],
            'call' => Opn::class . ':search',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/opn/engine' => [
            'method' => ['get'],
            'call' => Opn::class . ':engine',
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
        '/student_app/play/class_end' => [
            'method' => ['post'],
            'call' => Play::class . ':classEnd',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
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

        // 熊猫接口
        '/student_app/panda/get_student' => [
            'method' => ['get'],
            'call' => Panda::class . ':getStudent',
            'middles' => []
        ],
        '/student_app/panda/recent_played' => [
            'method' => ['get'],
            'call' => Panda::class . ':recentPlayed',
            'middles' => []
        ],
        '/student_app/panda/recent_detail' => [
            'method' => ['get'],
            'call' => Panda::class . ':recentDetail',
            'middles' => []
        ],
        '/student_app/panda/ai_end' => [
            'method' => ['post'],
            'call' => Panda::class . ':aiEnd',
            'middles' => []
        ],

        // 支付
        '/student_app/pay/create_bill' => [
            'method' => ['post'],
            'call' => Pay::class . ':createBill',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/pay/packages' => [
            'method' => ['get'],
            'call' => Pay::class . ':packages',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
        '/student_app/pay/bill_status' => [
            'method' => ['get'],
            'call' => Pay::class . ':billStatus',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        '/student_app/referral/list' => [
            'method' => ['get'],
            'call' => Referral::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],

        //APP内嵌音基接口
        '/student_app/exam/question_list' => [
            'method'  => ['get'],
            'call'    => Question::class . ':list',
            'middles' => [StudentAuthCheckMiddleWareForApp::class, AppApiForStudent::class]
        ],
    ];
}