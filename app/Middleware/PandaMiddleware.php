<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 3:41 PM
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\IPService;
use Slim\Http\Request;
use Slim\Http\Response;

class PandaMiddleware
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $clientIp = $_SERVER['HTTP_X_REAL_IP'];
        if (!IPService::validate($clientIp, 'panda')) {
            $errs = Valid::addErrors([], 'ip', 'ip_invalid');
            SimpleLogger::debug(__FILE__ . __LINE__ . " ip_invalid", [
                'ip' => $clientIp,
                'real' => $_SERVER['HTTP_X_REAL_IP'],
            ]);
            return $response->withJson($errs, 200);
        }

        return $next($request, $response);
    }
}