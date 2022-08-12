<?php


namespace App\Routers;

use App\Controllers\API\DawnCrm;
use App\Middleware\OrgWebMiddleware;
use App\Middleware\SignMiddleware;

class DawnCrmRouter extends RouterBase
{
    protected $logFilename = 'operation_dawn_crm.log';
    public    $middleWares = [OrgWebMiddleware::class, SignMiddleware::class];

    protected $uriConfig = [
        // 创建清晨转介绍关系
        '/dawn_crm/morning/create_referral' => ['method' => ['post'], 'call' => DawnCrm::class . ':morningCreateReferral'],
        // 清晨 - 获取用户推荐人
        '/dawn_crm/morning/student_referee' => ['method' => ['post'], 'call' => DawnCrm::class . ':getMorningStudentReferee'],
    ];

}