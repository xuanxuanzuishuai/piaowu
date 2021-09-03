<?php


namespace App\Middleware;


use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpUserWeiXinModel;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class RealReferralMinAppAuthCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token  = $request->getHeaderLine('token');
        $params = $request->getParams();
        if (empty($token)) {
            if (empty($params['wx_code'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            $appId    = Constants::REAL_APP_ID;
            $busiType = Constants::REAL_MINI_BUSI_TYPE;
            $userType = DssUserWeiXinModel::USER_TYPE_STUDENT;
            $wechat   = WeChatMiniPro::factory($appId, $busiType);
            $data     = $wechat->code2Session($params['wx_code']);
            if (empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            // 根据open id 获取用户信息
            $userInfo = ErpUserWeiXinModel::getUserInfoByOpenId($data['openid'], $busiType);
            $userId   = $userInfo['user_id'] ?? '';
            $token    = WechatTokenService::generateToken(
                $userId,
                $userType,
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
            $this->container['real_referral_miniapp_userid']   = $data['user_id'];
            $this->container['real_referral_miniapp_openid']   = $data['open_id'];
            $response = $next($request, $response);
            return $response;
        }
    }
}