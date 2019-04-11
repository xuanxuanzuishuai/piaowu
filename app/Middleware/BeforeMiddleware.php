<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/27
 * Time: 10:05 AM
 */
namespace App\Middleware;


class BeforeMiddleware extends MiddlewareBase
{
    public function __invoke($request, $response, $next)
    {
        return $next($request,$response);
    }
}