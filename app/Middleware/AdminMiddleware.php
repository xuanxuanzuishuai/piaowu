<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/16
 * Time: 2:01 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Models\EmployeeModel;
use Slim\Http\Request;
use Slim\Http\Response;

class AdminMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        if(!empty($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] != 'prod') {
            return $next($request, $response);
        }

        $token = $request->getCookieParam('admin_token');

        if (empty($token)) {
            return $response->withJson(['error' => '正式环境需要填写token']);
        }

        if ($token=='123') {
            return $next($request, $response);
        }

        $cacheEmployeeId = EmployeeModel::getEmployeeToken($token);
        if (empty($cacheEmployeeId)) {
            return $response->withJson(['error' => 'token不正确或已过期,需要正在登录中的账号的token']);
        }

        $employee = EmployeeModel::getById($cacheEmployeeId);
        if(empty($employee) || $employee['role_id'] != '-1') {
            return $response->withJson(['error' => '需要超级管理员账号的token']);
        }

        return $next($request, $response);
    }

}