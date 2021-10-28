<?php


namespace App\Routers;

use App\Controllers\API\Crm;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class CrmRouter extends RouterBase
{
    protected $logFilename = 'operation_crm.log';
    public $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

    protected $uriConfig = [
        '/crm/referral/referee_list' => ['method' => ['get', 'post'], 'call' => Crm::class . ':refereeList'],
        // 员工代替学生生成学生转介绍海报
        '/crm/employee/replace_student_create_poster' => ['method' => ['post'], 'call' => Crm::class . ':replaceStudentCreatePoster'],
    ];

}