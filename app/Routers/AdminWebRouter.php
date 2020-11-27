<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:15
 */

namespace App\Routers;

use App\Controllers\AdminWeb\EmployeeActivity;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\AdminWebMiddleware;

class AdminWebRouter extends RouterBase
{
    public $middleWares = [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class, AdminWebMiddleware::class];
    protected $logFilename = 'operation_admin_web.log';
    protected $uriConfig = [

        // 员工专项转介绍：
        '/admin_web/employee_activity/list'          => ['method' => ['get'], 'call' => EmployeeActivity::class . ':list'],
        '/admin_web/employee_activity/detail'        => ['method' => ['get'], 'call' => EmployeeActivity::class . ':detail'],
        '/admin_web/employee_activity/add'           => ['method' => ['post'], 'call' => EmployeeActivity::class . ':add'],
        '/admin_web/employee_activity/modify'        => ['method' => ['post'], 'call' => EmployeeActivity::class . ':modify'],
        '/admin_web/employee_activity/update_status' => ['method' => ['post'], 'call' => EmployeeActivity::class . ':updateStatus'],

        '/admin_web/employee_activity/active_list'   => ['method' => ['get'], 'call' => EmployeeActivity::class . ':activeList'],
        '/admin_web/employee_activity/get_poster'    => ['method' => ['get'], 'call' => EmployeeActivity::class . ':getPoster'],
    ];
}