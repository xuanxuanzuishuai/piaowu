<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:55 PM
 */

namespace App\Routers;


use App\Middleware\WeChatAuthCheckMiddleware;

class StudentWXRouter extends RouterBase
{
    protected $logFilename = 'operation_student_wx.log';
    public $middleWares = [WeChatAuthCheckMiddleware::class];
    protected $uriConfig = [

        /** 公共 */
        '/student_wx/common/js_config' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Common:getJsConfig', 'middles' => array()),

        /** 用户信息 */

        '/student_wx/student/register' => array('method'=>array('post'),'call'=>'\App\Controllers\StudentWX\Student:register', 'middles' => array()),
        '/student_wx/student/login' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:login', 'middles' => array('\App\Middleware\WeChatOpenIdCheckMiddleware')),
        '/student_wx/student/send_sms_code' => array('method'=>array('get'),'call'=>'\App\Controllers\StudentWX\Student:sendSmsCode', 'middles' => array()),
    ];
}