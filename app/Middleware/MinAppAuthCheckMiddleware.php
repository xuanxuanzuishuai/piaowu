<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/1
 * Time: 下午1:43
 */

namespace App\Middleware;
use App\Libs\HttpHelper;
use App\Libs\JWTUtils;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\StudentForMinAppService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Services\DictService;
use App\Libs\Constants;
use Slim\Http\StatusCode;

class MinAppAuthCheckMiddleware extends MiddlewareBase
{
    private $redisConn = null;

    private function getRedisConn()
    {
        if(empty($this->redisConn)) {
            $this->redisConn = RedisDB::getConn();
        }
        return $this->redisConn;
    }

    private function jwtToken($type, $userId, $name)
    {
        list($issuer, $audience, $expire, $signerKey) = DictService::getKeyValuesByArray(
            Constants::DICT_TYPE_SYSTEM_ENV,
            [
                Constants::DICT_KEY_JWT_ISSUER,
                Constants::DICT_KEY_JWT_AUDIENCE,
                Constants::DICT_KEY_JWT_EXPIRE,
                Constants::DICT_KEY_JWT_SIGNER_KEY,
            ]);

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
            ]);

        $jwt = new JWTUtils($issuer, $audience, $expire, $signerKey);

        return $jwt->verifyToken($token);
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $tokenHeader = $request->getHeader('token');
        $token = $tokenHeader[0] ?? null;

        //开发模式下，伪造一个token
        if(isset($_ENV['EXAM_ENV']) && $_ENV['EXAM_ENV'] == 'dev' && empty($token)) {
            list($token, $expire) = $this->jwtToken(0, '123456789', '');
            if(!empty($request->getParam('code'))) {
                return HttpHelper::buildResponse($response, ['token' => $token, 'expire' => (time() + $expire) * 1000]);
            }
        }

        if(empty($token)) {
            if(!empty($request->getParam('code'))) {
                $appId = $_ENV['EXAM_MINAPP_ID'];
                $secret = $_ENV['EXAM_MINAPP_SECRET'];
                $code = $request->getParam('code');
                $api = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
                $content = HttpHelper::requestJson($api);
                if($content === false) {
                    return $response->withJson(Valid::addAppErrors([], 'obtain_openid_fail'));
                }
                if(isset($content['errcode'])) {
                    SimpleLogger::error('obtain minapp openid error', $content);
                    return $response->withJson(Valid::addAppErrors([], $content['errmsg']));
                }

                list($token, $expire) = $this->jwtToken(0, $content['openid'], '');

                $this->getRedisConn()->set("{$content['openid']}.session_key", $content['session_key']);
                $this->getRedisConn()->expire("{$content['openid']}.session_key", $expire);

                //查询是否已经获取到了用户的手机号
                $hasMobile = StudentForMinAppService::hasMobile($content['openid']);

                //返回token和过期时间，过期时间单位是毫秒，同时返回是否已经获取了手机号，供前端判断是否需要弹窗
                return HttpHelper::buildResponse($response, [
                    'token'      => $token,
                    'expire'     => (time() + $expire) * 1000,
                    'has_mobile' => $hasMobile,
                    'open_id'    => $content['openid'],
                ]);
            } else {
                return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
            }
        } else {
            $data = $this->jwtVerifyToken($token);
            if(isset($data['code']) && $data['code'] == 0) {
                $this->container['exam_openid'] = $data['data']['user_id'];
                $this->container['exam_session_key'] = $this->getRedisConn()->get($data['data']['user_id'] . '.session_key');
                $response = $next($request, $response);
                return $response;
            }
            //token验证没有通过（token错误或过期）
            return $response->withJson(Valid::addAppErrors([], StatusCode::HTTP_UNAUTHORIZED)); // 401
        }
    }
}