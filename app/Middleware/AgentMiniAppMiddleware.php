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
use App\Models\AgentModel;
use App\Models\UserWeiXinModel;
use App\Services\AgentService;
use App\Services\UserService;
use App\Services\AgentMiniAppTokenService;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

/**
 * 检查小程序token
 * Class AgentMiniAppMiddleware
 * @package App\Middleware
 */
class AgentMiniAppMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--AgentMiniAppMiddleware--', []);
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;
        if (!empty($token)) {
            $userInfo = AgentMiniAppTokenService::getTokenInfo($token);
            if (empty($userInfo['open_id'])) {
                return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_UNAUTHORIZED);
            }
            $weixinInfo = UserService::getUserWeiXinInfoAndUserId($userInfo['app_id'], $userInfo['user_id'], $userInfo['open_id'], $userInfo['user_type'], UserWeiXinModel::BUSI_TYPE_AGENT_MINI);
            if (empty($weixinInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'need_bind'), StatusCode::HTTP_UNAUTHORIZED);
            }
            $agentInfo = AgentModel::getById($userInfo['user_id']);
            if (AgentService::checkAgentFreeze($agentInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'agent_freeze'), StatusCode::HTTP_UNAUTHORIZED);
            }
            $this->container['user_info'] = $userInfo;
            $this->container['open_id'] = $userInfo['open_id'];
            return $next($request, $response);
        }

        $code = $request->getParam('wx_code');
        if (!empty($code)) {
            // 获取open id
            $wechat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_OP_AGENT, UserWeiXinModel::BUSI_TYPE_AGENT_MINI);
            $data = $wechat->code2Session($code);
            if (empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], 'request_error'), StatusCode::HTTP_UNAUTHORIZED);
            }
            // 根据open id 获取用户信息
            $userInfo = AgentModel::getByOpenid($data['openid']);
            if (empty($userInfo)) {
                return $response->withJson(Valid::addAppErrors([], 'need_bind'), StatusCode::HTTP_UNAUTHORIZED);
            }
            // 返回TOKEN
            $token = AgentMiniAppTokenService::generateToken($userInfo['id'], UserWeiXinModel::USER_TYPE_AGENT, UserCenter::AUTH_APP_ID_OP_AGENT, $data['openid']);
            return HttpHelper::buildResponse($response, [
                'token'  => $token,
                'openid' => $data['openid']
            ]);
        }
        return $response->withJson(Valid::addAppErrors([], 'token_expired'), StatusCode::HTTP_UNAUTHORIZED);
    }
}
