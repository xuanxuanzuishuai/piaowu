<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;

class AdminLogMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // todo 增加操作日志
        $response = $next($request, $response);


        return $response;
    }
}