<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/24
 * Time: 17:16
 */

namespace App\Middleware;

use App\Libs\Valid;
use App\Services\IPService;
use Slim\Http\Request;
use Slim\Http\Response;

class AdminWebMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $clientIp = $request->getAttribute('ip_address');
        if (!IPService::validate($clientIp, 'org_web')) {
            $errs = Valid::addErrors([], 'ip', 'ip_invalid');
            return $response->withJson($errs, 200);
        }

        return $next($request, $response);
    }
}