<?php

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 根据token获取用户信息，如果获取不到用户信息，则返回token_expired报错，客户端需要捕获该报错然后调用login接口
 * Class WeChatAuthCheckMiddleware
 * @package App\Middleware
 */
class WeChatAuthCheckMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--WeChatAuthCheckMiddleware--', []);
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;

        $userInfo = null;
        if (!empty($token)) {
            $userInfo = WechatTokenService::getTokenInfo($token);
            if (empty($userInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
            }
        } else {
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }

        $this->container['user_info'] = $userInfo;
        $this->container["open_id"] = $userInfo["open_id"];
        $response = $next($request, $response);
        return $response;
    }
}
