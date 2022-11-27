<?php
namespace App\Middleware;

use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/** 检查token，如果token无效则根据code获取openid
 * Class WeChatOpenIdCheckMiddleware
 * @package App\Middleware
 */
class WeChatOpenIdCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--WeChatOpenIdCheckMiddleware--', []);

        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;
        $this->container["token"] = $token;


        $checkResult = $this::checkNeedWeChatCode($request);

        // 是否要跳转到微信端获取用户code
        $needWeChatCode = $checkResult["needWeChatCode"];
        $openId = $checkResult["openId"] ?? '';

        SimpleLogger::info("checkWeChatCode", ["open_id" => $openId, "need" => $needWeChatCode]);

        // 本地环境方便调试
        if ($_ENV['DEBUG_WEIXIN_SKIP_MIDDLEWARE'] == "1" and !empty($request->getParam("test_open_id"))) {
            $needWeChatCode = false;
            $openId = $request->getParam("test_open_id");
        }

        $this->container['open_id'] = $openId;

        // 需要获取微信code
        if ($needWeChatCode) {
            SimpleLogger::info('need weixin code:', []);
            return $response->withJson(Valid::addAppErrors([], 'need_code'), StatusCode::HTTP_OK);
        }

        $response = $next($request, $response);
        return $response;
    }
    
    public function checkNeedWeChatCode(Request $request) {
        $openId = null;
        $needWeChatCode = false;
        $code = $request->getParam('wx_code');
        // 已经获取微信的用户code，通过微信API获取openid
        if (!empty($code)) {
            $data = WeChatMiniPro::factory()->getWeixnUserOpenIDAndAccessTokenByCode($code);

            SimpleLogger::info("getWeixnUserOpenIDAndAccessTokenByCode", ["data" => $data]);
            if (!empty($data['openid'])) {
                    $openId = $data['openid'];
            } else {
                // 获取用户open id失败, 需要重新获取code换取open id
                $needWeChatCode = true;
            }
        } else {
            // 当前没有code
            $needWeChatCode = true;
        }
        return ["needWeChatCode" => $needWeChatCode, "openId" => $openId];
    }
}
