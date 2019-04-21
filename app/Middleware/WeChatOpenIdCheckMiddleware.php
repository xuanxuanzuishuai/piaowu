<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 17:17
 */

namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\RouterUtil;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;
use App\Services\WeChatService;

/** 检查token，如果token无效则获取openid
 * Class WeChatOpenIdCheckMiddleware
 * @package App\Middleware
 */
class WeChatOpenIdCheckMiddleware extends MiddlewareBase
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
        }

        $this->container['user_info'] = $userInfo;

        if (!empty($userInfo)) {
            // 刷新过期时间
            WeChatService::refreshToken($token);
            $this->container['open_id'] = $userInfo["open_id"];
        } else {
            $checkResult = $this::checkNeedWeChatCode($request, $userInfo);
            // 是否要跳转到微信端获取用户code
            $needWeChatCode = $checkResult["needWeChatCode"];
            $openId = $checkResult["openId"];

            // 本地环境方便调试
            if ($_ENV['DEBUG_WEIXIN_SKIP_MIDDLEWARE'] == "1" and !empty($request->getParam("test_open_id"))) {
                $needWeChatCode = false;
                $openId = $request->getParam("test_open_id");
            }

            $this->container['open_id'] = $openId;
            // 需要获取微信code
            if ($needWeChatCode) {
                SimpleLogger::info('get weixin code:', []);
                $currURL = RouterUtil::getCurrentURL();
                $currURL = urlencode($currURL);
                $uri = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $_ENV['TEACHER_WEIXIN_APP_ID'] .
                    '&redirect_uri=' . $currURL . '&response_type=code&scope=snsapi_base&state=123#wechat_redirect&is_we_chat_auth=1';

                SimpleLogger::info('uri: ' . $uri, []);
                $response = $response->withRedirect($uri, 301);
                return $response;
            }
        }

        $response = $next($request, $response);
        return $response;
    }
    
    public function checkNeedWeChatCode(Request $request, $userInfo) {
        $openId = null;
        $needWeChatCode = false;
        if (empty($userInfo)) {
            // 没有token或者token失效, 这时需要判断你http请求中是否包含code参数。
            // code参数是微信服务器提供的。用于换取用户的openid，其换取流程如下：
            // 当前页面-->判断url是否有code参数，如果没有-->跳转到微信server-->微信server跳回到当前页面并赋予code参数-->当前页面根据code值换取用户的openid
            $code = $request->getParam('code');
            $isWeChatAuth = $request->getParam("is_we_chat_auth");

            // 已经获取微信的用户code，通过微信API获取openid
            if (!empty($code) and $isWeChatAuth) {
                $data = WeChatService::getWeixnUserOpenIDAndAccessTokenByCode($code);

                if (!empty($data)) {
                    if (!empty($data['openid'])) {
                        $openId = $data['openid'];
                    } else {
                        $needWeChatCode = true;
                    }
                } else {
                    // 获取用户open id失败, 需要重新获取code换取open id
                    $needWeChatCode = true;
                }
            } else {
                // 当前没有code
                // 跳转到微信SNS API换取code值后，跳回当前页面
                $needWeChatCode = true;
            }
        } else {
            $openId = $userInfo["open_id"];
        }
        return ["needWeChatCode" => $needWeChatCode, "openId" => $openId];
    }
}
