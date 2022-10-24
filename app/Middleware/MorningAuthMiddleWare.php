<?php
/**
 * 清晨中间件
 * 清晨token验证
 */


namespace App\Middleware;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\SimpleLogger;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class MorningAuthMiddleWare extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // 获取header token
        try {
            $tokenHeader = $request->getHeaderLine('token');
            $authType = $request->getHeaderLine('auth-type') ?? 'wx';
            $token = $tokenHeader ?? null;
            if (empty($token)) {
                throw new RunTimeException(['token_expired']);
            }
            // 根据token换取用户的uuid
            $userInfo = (new Morning())->getTokenUuid($token, $authType);
            if (empty($userInfo) || empty($userInfo['uuid'])) {
                throw new RunTimeException(['token_expired']);
            }
            // 设置uuid
            $this->container['student_uuid'] = $userInfo['uuid'];
        } catch (RunTimeException $e) {
            SimpleLogger::info("Invalid Token Access", []);
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }
        return $next($request, $response);
    }
}
