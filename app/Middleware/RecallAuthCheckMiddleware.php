<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:31
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 如果有token，获取对应用户信息
 * Class RecallAuthCheckMiddleware
 * @package App\Middleware
 */
class RecallAuthCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--RecallAuthCheckMiddleware--', []);
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;

        $userInfo = null;
        if (!empty($token)) {
            $userInfo = WechatTokenService::getTokenInfo($token);
            if (empty($userInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
            }
            WechatTokenService::refreshToken($token);
            $this->container['user_info'] = $userInfo;
            $this->container["open_id"] = $userInfo["open_id"];
        }

        $currentUrl = $request->getUri()->getPath();

        $arr = [
            '/student_wx' => Constants::USER_TYPE_STUDENT,
            '/student_web' => Constants::USER_TYPE_STUDENT,
        ];
        $userType = $arr[$this->getURLPrefix($currentUrl)] ?? '';
        // 根据url确认哪种类型的用户，并检查当前token中保存的信息是否是该用户
        if (!empty($userInfo) && $userType != (int)$userInfo["user_type"]) {
            SimpleLogger::info("Invalid Token Access", [$userType, $userInfo]);
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }
        $response = $next($request, $response);
        return $response;
    }
}
