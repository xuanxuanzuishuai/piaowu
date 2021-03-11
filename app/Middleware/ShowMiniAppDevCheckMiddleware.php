<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2021/1/21
 * Time: 15:36
 */

namespace App\Middleware;


use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\SHA1;
use Slim\Http\Request;
use Slim\Http\Response;

class ShowMiniAppDevCheckMiddleware extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $params = $request->getParams();
        // 验证消息来自微信服务器
        if (!empty($params['echostr'])) {
            $sha1 = SHA1::getSHA1(
                [
                    $params['timestamp'],
                    $params['nonce'],
                    DictConstants::get(DictConstants::WECHAT_APP_PUSH_CONFIG, '8_10_token'),
                ]
            );
            if ($sha1 == $params['signature']) {
                $response->getBody()->write($params['echostr']);
                return $response;
            } else {
                SimpleLogger::error('echostr signature verify error', $params);
                $response->getBody()->write("error");
                return $response;
            }
        }
        return $next($request, $response);
    }
}
