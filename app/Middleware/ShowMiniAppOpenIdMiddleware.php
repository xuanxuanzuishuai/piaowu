<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 15:36
 */

namespace App\Middleware;

use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\UserWeiXinModel;
use App\Services\ShowMiniAppTokenService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 检查小程序token
 * Class AgentMiniAppMiddleware
 * @package App\Middleware
 */
class ShowMiniAppOpenIdMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--ShowMiniAppOpenIdMiddleware--', []);
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;
        if (!empty($token)) {
            $userInfo = ShowMiniAppTokenService::getTokenInfo($token);
            if (empty($userInfo['open_id'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED), StatusCode::HTTP_OK);
            }
            $this->container['open_id'] = $userInfo['open_id'];
            return $next($request, $response);
        }

        $code = $request->getParam('code');
        if (!empty($code)) {
            // 获取open id
            $wechat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeiXinModel::BUSI_TYPE_SHOW_MINI);
            $data = $wechat->code2Session($code);
            if (empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED), StatusCode::HTTP_OK);
            }
            // 返回TOKEN
            $token = ShowMiniAppTokenService::generateOpenIdToken($data['openid']);
            return HttpHelper::buildResponse($response, [
                'token'  => $token,
                'openid' => $data['openid']
            ]);
        }

        return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED), StatusCode::HTTP_OK);
    }
}
