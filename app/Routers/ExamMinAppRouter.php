<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/10/30
 * Time: 下午6:00
 */
namespace App\Routers;

use App\Controllers\ExamMinApp\Question;
use App\Controllers\ExamMinApp\Student;
use App\Controllers\ExamMinApp\Config;
use App\Middleware\MinAppAuthCheckMiddleware;
use App\Controllers\ExamMinApp\Msg;

class ExamMinAppRouter extends RouterBase
{
    protected $logFilename = 'dss_exam_minapp.log';

    protected $uriConfig = [
        '/exam/question/list' => [
            'method'  => ['get'],
            'call'    => Question::class . ':list',
            'middles' => [MinAppAuthCheckMiddleware::class],
        ],
        '/exam/question/detail' => [
            'method'  => ['get'],
            'call'    => Question::class . ':detail',
        ],
        '/exam/student/register' => [
            'method'  => ['post'],
            'call'    => Student::class . ':register',
            'middles' => [MinAppAuthCheckMiddleware::class],
        ],
        '/exam/config/config' => [
            'method'  => ['get'],
            'call'    => Config::class . ':config',
            'middles' => [MinAppAuthCheckMiddleware::class],
        ],
        '/exam/msg/notify' => [
            'method'  => ['get', 'post'],
            'call'    => Msg::class . ':notify'
        ],
    ];
}