<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/28
 * Time: 8:29 PM
 */

namespace App\Middleware;


use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class OrgResPrivilegeCheckMiddleWareForApp extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var Response $response */
        $response = $next($request, $response);

        if ($this->container['need_res_privilege'] && empty($this->container['teacher'])) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['no res privilege']);
            $result = Valid::addAppErrors([], 'no_res_privilege');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        SimpleLogger::info(__FILE__ . ":" . __LINE__, [
            'middleWare' => 'OrgResPrivilegeCheckMiddleWareForApp'
        ]);

        return $response;
    }
}