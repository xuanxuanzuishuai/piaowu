<?php

namespace App;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Middleware\AfterMiddleware;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$arr = array(

        '/boss/employee/login' => array('method' => array('post'), 'call' => '\App\Controllers\Employee:login', 'middles' => array()),
        '/boss/employee/logout' => array('method' => array('post'), 'call' => '\App\Controllers\MyClass:method2', 'middles' => array('\App\Middleware\EmployeeAuthCheckMiddleware')),
        '/boss/employee/list' => array('method' => array('get'), 'call' => '\App\Controllers\Employee:method2', 'middles' => array('\App\Middleware\EmployeePrivilegeMiddleware','\App\Middleware\EmployeeAuthCheckMiddleware')),
        '/boss/privilege/employee_menu' => array('method' => array('post'), 'call' => '\App\Controllers\MyClass:method2', 'middles' => array('\App\Middleware\EmployeeAuthCheckMiddleware'))
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
        $r->add(new AfterMiddleware($app->getContainer()));
    }

    $response = $next($request, $response);
    $endTime = Util::microtime_float();
    $t = $endTime - $startTime;
    SimpleLogger::debug(__FILE__ . ":" . __LINE__, ["== path: $uri END ({$t}) =="]);

    return $response;
});


