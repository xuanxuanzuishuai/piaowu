<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/9/1
 * Time: 15:41
 */

namespace App\Routers;


use App\Controllers\StudentWX\Poster;
use App\Controllers\StudentWX\RealPoster;
use App\Middleware\RealStudentWeChatAuthCheckMiddleware;
use App\Controllers\StudentWX\Student;
use App\Controllers\StudentWX\Activity;

/**
 * 真人业务线学生app端接口路由文件
 * Class StudentWXRouter
 * @package App\Routers
 */
class RealStudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_real_student_app.log';
    public $middleWares = [RealStudentWeChatAuthCheckMiddleware::class];
    protected $uriConfig = [
        // 月月有奖 && 周周领奖
        '/real_student_app/poster/list' => ['method' => ['post'], 'call' => RealPoster::class . ':list'],
    ];
}
