<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/21
 * Time: 10:56
 */

namespace App\Middleware;

use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Constants;
use Slim\Http\StatusCode;

class ReferralMinAppAuthCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $token = $request->getHeaderLine('token');
        $params = $request->getParams();
        if (empty($token)) {
            if (empty($params['wx_code'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            $appId = Constants::SMART_APP_ID;
            $busiType = Constants::SMART_MINI_BUSI_TYPE;
            $userType = DssUserWeiXinModel::USER_TYPE_STUDENT;
            $wechat = WeChatMiniPro::factory($appId, $busiType);
            $data = $wechat->code2Session($params['wx_code']);
            if (empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            // 根据open id 获取用户信息
            $userInfo = DssUserWeiXinModel::getByOpenid($data['openid'], $appId, $userType, $busiType);
            $userId = $userInfo['user_id'] ?? '';
            $studentInfo = DssStudentModel::getById($userId);
            $token = WechatTokenService::generateToken(
                $userId,
                $userType,
                $appId,
                $data['openid'],
                $studentInfo['uuid']
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
            $this->container['referral_miniapp_userid']   = $data['user_id'];
            $this->container['referral_miniapp_openid']   = $data['open_id'];
            $this->container['referral_miniapp_usertype'] = $data['user_type'];
            $this->container['referral_miniapp_appid']    = $data['app_id'];
            $response = $next($request, $response);
            return $response;
        }
    }
}