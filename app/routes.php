<?php

namespace App;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array(

    '/employee/auth/tokenlogin' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:tokenlogin', 'middles' => array()),
    '/employee/auth/signout' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Auth:signout', 'middles' => array('\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/auth/usercenterurl' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Auth:usercenterurl', 'middles' => array()),

    '/employee/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setPwd', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/userSetPwd' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:userSetPwd', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setExcludePrivilege' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setExcludePrivilege', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/setExtendPrivilege' => array('method' => array('post'), 'call' => '\App\Controllers\Employee\Employee:setExtendPrivilege', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/employee/employee/getEmployeeListWithRole' => array('method' => array('get'), 'call' => '\App\Controllers\Employee\Employee:getEmployeeListWithRole', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/privilege/employee_menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:employee_menu', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Privilege:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilege/menu' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Privilege:menu', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/privilegeGroup/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilegeGroup/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/privilegeGroup/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\GroupPrivilege:post', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),

    '/privilege/role/list' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:list', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/role/detail' => array('method' => array('get'), 'call' => '\App\Controllers\Privilege\Role:detail', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '/privilege/role/modify' => array('method' => array('post'), 'call' => '\App\Controllers\Privilege\Role:modify', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleWare', '\App\Middleware\EmployeeAuthCheckMiddleWare')),
    '' => array(),

);

/** @var App $app */
$app->add(function (Request $request, Response $response, $next) use ($app, $arr) {
    $uri = $request->getUri()->getPath();
    $startTime = Util::microtime_float();

    $method = $request->getMethod();
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== method: $method, path: $uri START =="]);

    if (!empty($arr[$uri])) {
        $r = $app->map($arr[$uri]['method'], $uri, $arr[$uri]['call']);
        if (!empty($arr[$uri]['middles']) && is_array($arr[$uri]['middles'])) {
            foreach ($arr[$uri]['middles'] as $middle)
                $r->add(new $middle($app->getContainer()));
        }
        //$r->add(new AfterMiddleware($app->getContainer()));
    }

    $response = $next($request, $response);
    $endTime = Util::microtime_float();
    $t = $endTime - $startTime;
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== path: $uri END ({$t}) =="]);

    return $response;
});


