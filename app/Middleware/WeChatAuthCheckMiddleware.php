<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:31
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Models\UserWeixinModel;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/** 这个必须要和WeChatOpenIdCheckMiddleware连用，
 * Class WeChatAuthCheckMiddleware
 * @package App\Middleware
 */
class WeChatAuthCheckMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--WeChatAuthCheckMiddleware--', []);

        $userInfo = $this->container['user_info'];
        $openId = $this->container["open_id"];
        SimpleLogger::info("WeChatAuthCheckMiddleware", ["user_info" => $userInfo, "open_id" => $openId]);

        if (empty($userInfo)) {
            // 如果既没有合法的token又没有openid，这时候肯定是有问题。不应该走到这
            if (empty($openId)) {
                return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
            }

            $bound_info = UserWeixinModel::getBoundInfoByOpenId($openId);
            // 没有找到该openid的绑定关系
            if (empty($bound_info)) {
                return $response->withJson(Valid::addAppErrors([], 'need_bound'), StatusCode::HTTP_OK);
            }

        }

        $response = $next($request, $response);
        return $response;
    }
}
