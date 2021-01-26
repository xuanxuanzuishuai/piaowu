<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;
use App\Controllers\StudentApp\App;
use App\Controllers\StudentApp\Auth;
use App\Controllers\StudentApp\Poster;
use App\Controllers\StudentWX\Student;
use App\Middleware\AppAuthMiddleWare;

class StudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_student.log';

    protected $uriConfig = [
        '/student_app/app/country_code' => [
            'method' => ['get'],
            'call' => App::class . ':countryCode',
        ],
        '/student_app/auth/getTokenByOtherToken' => [
            'method' => ['get'],
            'call' => Auth::class . ':getTokenByOtherToken',
        ],
        '/student_app/auth/accountDetail' => [
            'method' => ['get'],
            'call' => Auth::class . ':accountDetail',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/getPoster' => [
            'method' => ['get'],
            'call' => Poster::class . ':templatePosterList',
            'middles' => [AppAuthMiddleWare::class]
        ],
        '/student_app/poster/getTemplateWord' => [
            'method' => ['get'],
            'call' => Poster::class . ':getTemplateWord',
            'middles' => [AppAuthMiddleWare::class]
        ]

    ];
}