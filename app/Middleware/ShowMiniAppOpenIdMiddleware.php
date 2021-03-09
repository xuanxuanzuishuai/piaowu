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
use App\Services\UserService;
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
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_UNAUTHORIZED);
            }
            $weixinInfo = UserService::getUserWeiXinInfoAndUserId($userInfo['app_id'], $userInfo['user_id'], $userInfo['open_id'], $userInfo['user_type'], UserWeiXinModel::BUSI_TYPE_SHOW_MINI);
            if (empty($weixinInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'need_bind'), StatusCode::HTTP_UNAUTHORIZED);
            }
            ShowMiniAppTokenService::refreshUserToken($userInfo['user_id'], $userInfo['user_type'], $userInfo['app_id'], $userInfo['open_id']);
            ShowMiniAppTokenService::refreshToken($token);
            $this->container['user_info'] = $userInfo;
            $this->container['open_id'] = $userInfo['open_id'];
            return $next($request, $response);
        }

        $code = $request->getParam('wx_code');
        if (!empty($code)) {
            // 获取open id
            $wechat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, UserWeiXinModel::BUSI_TYPE_SHOW_MINI);
            $data = $wechat->code2Session($code);
            if (empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], 'request_error'), StatusCode::HTTP_UNAUTHORIZED);
            }
            // 返回TOKEN
            $token = ShowMiniAppTokenService::generateToken(0, UserWeiXinModel::USER_TYPE_STUDENT, UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, $data['openid']);
            return HttpHelper::buildResponse($response, [
                'token'  => $token,
                'openid' => $data['openid']
            ]);
        }

        return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_UNAUTHORIZED);
    }
}
