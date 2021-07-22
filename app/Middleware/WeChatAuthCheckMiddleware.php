<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:31
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\SimpleLogger;
use App\Services\UserService;
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
            //当前系统对应的应用busi_type
            $arr = [
                Constants::SMART_APP_ID => Constants::SMART_WX_SERVICE
            ];
            $busiType = $arr[$userInfo['app_id']] ?? Constants::SMART_WX_SERVICE;
            if (!empty($userInfo['open_id'])) {
                $weixinInfo = UserService::getUserWeiXinInfoAndUserId($userInfo['app_id'], $userInfo['user_id'], $userInfo['open_id'], $userInfo['user_type'], $busiType);
                //是否还有绑定关系
                if (empty($weixinInfo)) {
                    $weixinInfo = (new Dss())->getWeixinInfo([
                        'user_id' => $userInfo['user_id'],
                        'open_id' => $userInfo['open_id'],
                        'user_type' => $userInfo['user_type'],
                        'busi_type' => $busiType
                    ]);
                }
                if (empty($weixinInfo)) {
                    return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
                }
                SimpleLogger::info('UserInfo: ', ["token" => $token, "userInfo" => $userInfo]);
            }
        } else {
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }

        $currentUrl = $request->getUri()->getPath();

        $arr = [
            '/student_wx' => Constants::USER_TYPE_STUDENT
        ];
        $userType = $arr[$this->getURLPrefix($currentUrl)] ?? '';

        // 根据url确认哪种类型的用户，并检查当前token中保存的信息是否是该用户
        if ($userType != (int)$userInfo["user_type"]){
            SimpleLogger::info("Invalid Token Access", [$userType, (int)$userInfo["user_type"]]);
            return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_OK);
        }
        WechatTokenService::refreshToken($token);
        $this->container['user_info'] = $userInfo;
        $this->container["open_id"] = $userInfo["open_id"];
        $response = $next($request, $response);
        return $response;
    }
}
