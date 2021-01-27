<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 15:36
 */

namespace App\Middleware;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\UserWeiXinModel;
use Slim\Http\Request;
use App\Libs\Valid;
use Slim\Http\StatusCode;
use Slim\Http\Response;

class AgentMiniAppOpenIdMiddleware extends MiddlewareBase
{

    public function __invoke(Request $request, Response $response, $next)
    {
        SimpleLogger::info('--AgentMiniAppOpenIdMiddleware--', []);
        $code = $request->getParam('wx_code');
        if (empty($code)) {
            return $response->withJson(Valid::addAppErrors([], 'need_wx_code'), StatusCode::HTTP_OK);
        }
        try {
            $wechat = WeChatMiniPro::factory(UserCenter::AUTH_APP_ID_OP_AGENT, UserWeiXinModel::BUSI_TYPE_AGENT_MINI);
            $loginData = $wechat->code2Session($code);
            if (!empty($loginData['errcode']) || empty($loginData['openid'])) {
                SimpleLogger::error('GET DATA BY WX CODE ERROR', [$loginData, $code]);
                return $response->withJson(Valid::addAppErrors([], 'request_error'), StatusCode::HTTP_OK);
            }
            $this->container['open_id'] = $loginData['openid'];
            $this->container['unionid'] = $loginData['unionid'];

        } catch (RunTimeException $e) {
            return $response->withJson(Valid::addAppErrors([], 'request_error'), StatusCode::HTTP_OK);
        }
        return $next($request, $response);
    }
}
