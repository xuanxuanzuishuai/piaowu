<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/21
 * Time: 2:56 PM
 */

namespace App\Routers;

use App\Controllers\API\OSS;
use App\Controllers\API\UICtl;
use App\Controllers\Employee\Auth;
use App\Controllers\Employee\Employee;
use App\Controllers\OrgWeb\Dept;
use App\Controllers\OrgWeb\Employee as OrgWebEmployee;
use App\Middleware\EmployeeAuthCheckMiddleWare;
use App\Middleware\EmployeePrivilegeMiddleWare;
use App\Middleware\OrgWebMiddleware;

class OrgWebRouter extends RouterBase
{
    public $middleWares = [EmployeePrivilegeMiddleWare::class, EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class];
    protected $logFilename = 'operation_org_web.log';
    protected $uriConfig = [

        '/api/uictl/dropdown' => ['method' => ['get'], 'call' => UICtl::class . ':dropdown', 'middles' => [OrgWebMiddleware::class]],
        '/api/oss/callback' => ['method' => ['post'], 'call' => OSS::class . ':callback', 'middles' => [OrgWebMiddleware::class]],


        '/employee/auth/tokenlogin' => ['method' => ['post'], 'call' => Auth::class . ':tokenlogin', 'middles' => [OrgWebMiddleware::class]],
        '/employee/auth/signout' => ['method' => ['post'], 'call' => Auth::class . ':signout', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],
        '/employee/auth/usercenterurl' => ['method' => ['get'], 'call' => Auth::class . ':usercenterurl', 'middles' => [OrgWebMiddleware::class]],

        '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list'),
        //list for org
        '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail'),
        '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify'),
        '/employee/employee/set_seat' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setSeat'),
        '/employee/employee/del_seat' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:delSeat'),
        '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd'),
        '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd'),
        '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole'),
        //机构批量分配课管(course consultant)
        '/org_web/employee/user_external_information' => ['method' => ['post'], 'call' => Employee::class . ':userExternalInformation', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],
        '/org_web/employee/get_external_information' => ['method' => ['get'], 'call' => Employee::class . ':getExternalInformation', 'middles' => [EmployeeAuthCheckMiddleWare::class, OrgWebMiddleware::class]],


        '/org_web/employee/get_dept_members' => [
            'method' => ['get'],
            'call' => OrgWebEmployee::class . ':getDeptMembers',
        ],
        '/privilege/privilege/employee_menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:employee_menu'),
        '/privilege/privilege/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:list'),
        '/privilege/privilege/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:detail'),
        '/privilege/privilege/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Privilege:modify'),
        '/privilege/privilege/menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:menu'),


        '/privilege/privilegeGroup/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:list'),
        '/privilege/privilegeGroup/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:detail'),
        '/privilege/privilegeGroup/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:modify'),


        '/privilege/role/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:list'),
        '/privilege/role/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:detail'),
        '/privilege/role/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Role:modify'),


        '/area/area/getByParentCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByParentCode'),
        '/area/area/getByCode' => array('method' => array('get'), 'call' => '\App\Controllers\Area\Area:getByCode'),

        // 部门
        '/org_web/dept/tree' => ['method' => ['get'], 'call' => Dept::class . ':tree'],
        '/org_web/dept/list' => ['method' => ['get'], 'call' => Dept::class . ':list'],
        '/org_web/dept/modify' => ['method' => ['post'], 'call' => Dept::class . ':modify'],
        '/org_web/dept/dept_privilege' => ['method' => ['get'], 'call' => Dept::class . ':deptPrivilege'],
        '/org_web/dept/privilege_modify' => ['method' => ['post'], 'call' => Dept::class . ':privilegeModify'],

    ];
}