<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/22
 * Time: 4:17 PM
 */

namespace App\Routers;

use App\Controllers\StudentApp\Opn;
use App\Controllers\StudentWeb\Auth;
use App\Controllers\StudentWeb\Collection;
use App\Controllers\StudentWeb\Pay;
use App\Controllers\StudentWeb\Referral;
use App\Controllers\StudentWeb\ReviewCourse;
use App\Controllers\WeChatCS\WeChatCS;
use App\Middleware\AppApiForStudent;
use App\Middleware\OpnResMiddlewareForWeb;

class StudentWebRouter extends RouterBase
{
    protected $logFilename = 'dss_student_web.log';

    protected $uriConfig = [
        '/student_web/auth/wx_app_id' => [
            'method' => ['get'],
            'call' => Auth::class . ':getWxAppId',
            'middles' => []
        ],

        '/student_web/auth/register' => [
            'method' => ['post'],
            'call' => Auth::class . ':register',
            'middles' => []
        ],
        '/student_web/auth/ai_referrer_register' => [ //AI转介绍注册
            'method' => ['post'],
            'call' => Auth::class . ':AIReferrerRegister',
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

        // 获取微信客服
        '/student_web/pay/wechatcs' => [
            'method' => ['get'],
            'call' => WeChatCS::class.':getWeChatCS',
            'middles' => []
        ],

        '/student_web/referral/referrer_info' => [
            'method' => ['get'],
            'call' => Referral::class . ':referrerInfo',
            'middles' => []
        ],

        '/student_web/opn/lesson_resources' => [
            'method' => ['get'],
            'call' => Opn::class . ':lessonResources',
            'middles' => [OpnResMiddlewareForWeb::class,
                AppApiForStudent::class]
        ],
        '/student_web/review_course/get_task_review' => [
            'method' => ['get'],
            'call' => ReviewCourse::class . ':getTaskReview',
        ],
        // 获取用户所属集合的微信信息
        '/student_web/pay/collection_data' => [
            'method' => ['get'],
            'call' => Collection::class.':getCollectionData',
            'middles' => []
        ],
    ];
}