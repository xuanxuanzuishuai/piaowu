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

class DDExamMinAppRouter extends RouterBase
{
    protected $logFilename = 'dss_dd_exam_minapp.log';

    protected $uriConfig = [
        '/dd_exam/question/baseList' => [
            'method'  => ['get'],
            'call'    => Question::class . ':baseList',
        ],
        '/dd_exam/question/categoryRelateQuestions' => [
            'method'  => ['get'],
            'call'    => Question::class . ':categoryRelateQuestions',
        ],
        '/dd_exam/question/list' => [
            'method'  => ['get'],
            'call'    => Question::class . ':list',
        ],
        '/dd_exam/question/detail' => [
            'method'  => ['get'],
            'call'    => Question::class . ':detail',
        ],
        '/dd_exam/student/register' => [
            'method'  => ['post'],
            'call'    => Student::class . ':register',
        ],
        '/dd_exam/config/config' => [
            'method'  => ['get'],
            'call'    => Config::class . ':config',
        ],
        '/dd_exam/msg/notify' => [
            'method'  => ['get', 'post'],
            'call'    => Msg::class . ':notify'
        ],
    ];
}