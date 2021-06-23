<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/21
 * Time: 10:56
 */

namespace App\Middleware;

use App\Libs\HttpHelper;
use App\Libs\OpernCenter;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Constants;
use Slim\Http\StatusCode;

class AIPlayMiniAppAuthCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $version = $request->getHeaderLine('version');
        $this->container['version'] = empty($version) ? null : $version;

        $this->container['opn_pro_ver'] = $this->container['version'] ?? OpernCenter::DEFAULT_APP_VER;
        $this->container['opn_auditing'] = 1;
        $this->container['opn_publish'] = 1;

        $token = $request->getHeaderLine('token');
        if (empty($token)) {
            $wxCode = $request->getParam('wx_code');
            if (empty($wxCode)) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            $appId    = Constants::SMART_APP_ID;
            $userType = DssUserWeiXinModel::USER_TYPE_STUDENT;
            $busiType = DssUserWeiXinModel::BUSI_TYPE_AI_PLAY_MINAPP;
            $wx       = WeChatMiniPro::factory($appId, $busiType);
            $data     = $wx->code2Session($wxCode);
            if (!empty($data['errcode'])) {
                SimpleLogger::error('obtain ai play mini app openid error', $data);
                return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'));
            }
            // 根据open id 获取用户信息
            $userInfo = DssUserWeiXinModel::getByOpenid($data['openid'], $appId, $userType, $busiType);
            $userId   = $userInfo['user_id'] ?? '';
            $token    = WechatTokenService::generateToken(
                $userId,
                $busiType,
                $appId,
                $data['openid']
            );
            //返回token
            return HttpHelper::buildResponse($response, [
                'token'   => $token,
                'open_id' => $data['openid']
            ]);
        } else {
            $data = WechatTokenService::getTokenInfo($token);
            if (empty($data)) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            $this->container['ai_play_miniapp_userid']   = $data['user_id'];
            $this->container['ai_play_miniapp_openid']   = $data['open_id'];
            $this->container['ai_play_miniapp_usertype'] = $data['user_type'];
            $this->container['ai_play_miniapp_appid']    = $data['app_id'];
            return $next($request, $response);
        }
    }
}
