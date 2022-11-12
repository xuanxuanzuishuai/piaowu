<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/27
 * Time: 下午7:52
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Services\EmployeeTokenService;

class EmployeeAuthCheckMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {

        /** @var array $token */
        $token = $request->getCookieParam('token');
        if (empty($token)) {
            $token = $request->getHeader('token')[0];
            if (empty($token) && $request->getMethod() == 'GET'){
                $params = $request->getParams();
                $token = $params['token'] ?? '';
            }
        }
        SimpleLogger::info('employee token',[$token]);

        if (empty($token)) {
            return $response->withJson(Valid::addErrors(['code' => -1], 'auth_check1', 'token_can_not_empty'));
        }else{
            $cacheEmployeeInfo = EmployeeTokenService::getTokenInfo($token);
        }

        if (empty($cacheEmployeeInfo)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__, ['code' => 'Employee had logouted', 'errs' => []]);
            return $response->withJson(Valid::addErrors(['code' => -1], 'auth_check', 'employee_has_logout'));
        }
        $this->container['employee'] = $cacheEmployeeInfo;
        $response = $next($request, $response);

        return $response;
    }
}