<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;
use App\Middleware\WeChatOpenIdCheckMiddleware;
use App\Controllers\StudentWX\Student;

class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'operation_student_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/student_wx/common/js_config' => ['method' => ['get'], 'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => []],

        /** 用户信息 */

        '/student_wx/student/register' => ['method'=>['post'],'call' => Student::class . ':register', 'middles' => []],
        '/student_wx/student/login'    => ['method'=>['get'],'call'=>Student::class . ':login', 'middles' => [WeChatOpenIdCheckMiddleware::class]],
        '/student_wx/student/send_sms_code' => ['method'=>['get'],'call'=>Student::class.':sendSmsCode', 'middles' => []],

        // 获取分享海报：
        '/student_wx/employee_activity/poster' => ['method' => ['get'], 'call' => Student::class . ':getPosterList'],
    ];
}