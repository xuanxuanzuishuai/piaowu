<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/14
 * Time: 下午2:01
 */

namespace App\Routers;

use App\Controllers\ClassroomApp\Auth;
use App\Controllers\ClassroomApp\App;
use App\Controllers\ClassroomApp\Schedule;
use App\Middleware\ClassroomAppMiddleware;
use App\Middleware\ClassroomScheduleMiddleware;

class ClassroomAppRouter extends RouterBase
{
    protected $logFilename = 'dss_classroom_app.log';

    protected $uriConfig = [
        '/classroom_app/auth/login' => [
            'method'  => ['post'],
            'call'    => Auth::class . ':login',
        ],
        '/classroom_app/app/init' => [
            'method'  => ['post'],
            'call'    => App::class . ':init',
            'middles' => [ClassroomAppMiddleware::class],
        ],
        '/classroom_app/app/version_check' => [
            'method'  => ['get'],
            'call'    => App::class . ':versionCheck',
            'middles' => [ClassroomAppMiddleware::class],
        ],
        '/classroom_app/schedule/start' => [
            'method'  => ['post'],
            'call'    => Schedule::class . ':start',
            'middles' => [ClassroomAppMiddleware::class],
        ],
        '/classroom_app/schedule/end' => [
            'method'  => ['post'],
            'call'    => Schedule::class . ':end',
            'middles' => [ClassroomAppMiddleware::class, ClassroomScheduleMiddleware::class],
        ],
    ];
}