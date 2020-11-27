<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:52 PM
 */

namespace App\Routers;
use App\Controllers\StudentApp\App;

class StudentAppRouter extends RouterBase
{
    protected $logFilename = 'operation_student.log';

    protected $uriConfig = [
        '/student_app/app/country_code' => [
            'method' => ['get'],
            'call' => App::class . ':countryCode',
        ]
    ];
}