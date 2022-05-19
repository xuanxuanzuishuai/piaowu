<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:31
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Services\StudentWebCommonService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 根据token获取用户信息，如果获取不到用户信息，则返回token_expired报错
 * Class WeChatAuthCheckMiddleware
 * @package App\Middleware
 */
class StudentWebAuthMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--StudentWebAuthMiddleWare--', []);
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;
        $userInfo = null;
        if (!empty($token)) {
            $userInfo = StudentWebCommonService::getTokenInfo($token);
            if (empty($userInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
            }
        } else {
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }
        StudentWebCommonService::refreshUserToken($userInfo['uuid'], $userInfo['app_id']);
        StudentWebCommonService::refreshToken($token);
        $this->container['user_info'] = $userInfo;
        $response = $next($request, $response);
        return $response;
    }
}
