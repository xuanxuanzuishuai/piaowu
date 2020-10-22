<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/21
 * Time: 10:56
 */

namespace App\Middleware;

use App\Libs\HttpHelper;
use App\Libs\JWTUtils;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\StudentForMinAppService;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Services\DictService;
use App\Libs\Constants;
use Slim\Http\StatusCode;

class ReferralMinAppAuthCheckMiddleware extends MiddlewareBase
{
    private function jwtToken($type, $userId, $name)
    {
        list($issuer, $audience, $expire, $signerKey) = DictService::getKeyValuesByArray(
            Constants::DICT_TYPE_SYSTEM_ENV,
            [
                Constants::DICT_KEY_JWT_ISSUER,
                Constants::DICT_KEY_JWT_AUDIENCE,
                Constants::DICT_KEY_JWT_EXPIRE,
                Constants::DICT_KEY_JWT_SIGNER_KEY,
            ]
        );

        $jwt = new JWTUtils($issuer, $audience, $expire, $signerKey);
        $token = $jwt->getToken($type, $userId, $name);

        return [$token, $expire];
    }

    private function jwtVerifyToken($token)
    {
        list($issuer, $audience, $expire, $signerKey) = DictService::getKeyValuesByArray(
            Constants::DICT_TYPE_SYSTEM_ENV,
            [
                Constants::DICT_KEY_JWT_ISSUER,
                Constants::DICT_KEY_JWT_AUDIENCE,
                Constants::DICT_KEY_JWT_EXPIRE,
                Constants::DICT_KEY_JWT_SIGNER_KEY,
            ]
        );

        $jwt = new JWTUtils($issuer, $audience, $expire, $signerKey);

        return $jwt->verifyToken($token);
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;

        //开发模式下，伪造一个token
        if (isset($_ENV['ENV_NAME']) && $_ENV['ENV_NAME'] == 'dev' && empty($token)) {
            list($token, $expire) = $this->jwtToken(0, '1234567890', '');
            if (!empty($request->getParam('code'))) {
                return HttpHelper::buildResponse($response, ['token' => $token, 'expire' => (time() + $expire) * 1000]);
            }
        }

        if (empty($token)) {
            if (empty($request->getParam('code'))) {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
            $appId   = $_ENV['REFERRAL_LANDING_APP_ID'];
            $secret  = $_ENV['REFERRAL_LANDING_APP_SECRET'];
            $code    = $request->getParam('code');
            $api     = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
            $content = HttpHelper::requestJson($api);
            if ($content === false) {
                return $response->withJson(Valid::addAppErrors([], 'obtain_openid_fail'));
            }
            if (isset($content['errcode'])) {
                SimpleLogger::error('obtain minapp openid error', $content);
                return $response->withJson(Valid::addAppErrors([], $content['errmsg']));
            }

            list($token, $expire) = $this->jwtToken(0, $content['openid'], '');

            WechatService::setSessionKeyWithExpire($content['openid'], $content['session_key'], $expire);

            $hasMobile = StudentForMinAppService::hasMobile($content['openid']);
            //返回token和过期时间，过期时间单位是毫秒
            return HttpHelper::buildResponse($response, [
                'token'      => $token,
                'expire'     => (time() + $expire) * 1000,
                'has_mobile' => $hasMobile,
                'open_id'    => $content['openid'],
            ]);
        } else {
            $data = $this->jwtVerifyToken($token);
            if (isset($data['code']) && $data['code'] == 0) {
                $this->container['referral_landing_openid'] = $data['data']['user_id'];
                $this->container['referral_landing_session_key'] = RedisDB::getConn()->get($data['data']['user_id'] . '.session_key');
                $response = $next($request, $response);
                return $response;
            }
            //token验证没有通过（token错误或过期）
            return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
        }
    }
}