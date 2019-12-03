<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:31
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;
use App\Services\WeChatService;

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
            $userInfo = WeChatService::getTokenInfo($token);
            if (empty($userInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
            }
            SimpleLogger::info('UserInfo: ', ["token" => $token, "userInfo" => $userInfo]);
        } else {
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }

        $currentUrl = $request->getUri()->getPath();

        switch ($this->getURLPrefix($currentUrl)) {
            case '/student_wx': // 学生微信公众号
                $user_type = WeChatService::USER_TYPE_STUDENT;
                break;
            case '/teacher_wx': // 老师微信公众号
                $user_type = WeChatService::USER_TYPE_TEACHER;
                break;
            case '/classroom_teacher_wx': // TheONE国际钢琴课老师端
                $user_type = WeChatService::USER_TYPE_TEACHER;
                break;
            default:
                $user_type = WeChatService::USER_TYPE_STUDENT_ORG;
        }

        // 根据url确认哪种类型的用户，并检查当前token中保存的信息是否是该用户
        if ($user_type != (int)$userInfo["user_type"]){
            SimpleLogger::info("Invalid Token Access", [$user_type, (int)$userInfo["user_type"]]);
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }
        WeChatService::refreshToken($token);
        $this->container['user_info'] = $userInfo;
        $this->container["open_id"] = $userInfo["open_id"];
        $response = $next($request, $response);
        return $response;
    }
}
