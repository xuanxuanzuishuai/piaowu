<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/15
 * Time: 16:47
 */

namespace App\Routers;

use App\Controllers\Real\LandingPromotion;
use App\Controllers\RealStudentOverseas\Delivery;
use App\Controllers\Real\StudentAuth;
use App\Controllers\StudentWeb\RealStudent;

class RealStudentWebRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_web.log';
    public $middleWares = [];
    protected $uriConfig = [
        //注册登陆
        '/real_student_web/auth/sms_code_register' => ['method' => ['post'], 'call' => StudentAuth::class . ':smsCodeRegister', 'middles' => []],
        '/real_student_web/auth/send_sms_code' => ['method' => ['post'], 'call' => StudentAuth::class . ':sendSmsCode', 'middles' => []],
        //国内：H5推广落地页路由
        '/real_student_web/landing/main_course_promoted_record_v1' => ['method' => ['post'], 'call' => LandingPromotion::class . ':mainCoursePromotedRecordV1', 'middles' => []],

        //海外：H5推广落地页路由
        '/real_student_web/landing_overseas/delivery_v1' => ['method' => ['post'], 'call' => Delivery::class . ':deliveryV1', 'middles' => []],

        //激活例子
        '/real_student_web/landing/active_leads' => ['method' => ['post'], 'call' => RealStudent::class . ':activeLeads', 'middles' => []],
    ];
}