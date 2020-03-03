<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 3:19 PM
 */

namespace App\Middleware;

use App\Libs\HttpHelper;
use Slim\Http\Request;
use Slim\Http\Response;

class WeChatSignatureCheck extends MiddlewareBase
{
    public function __invoke(Request $request, Response $response, $next)
    {
        $params = $request->getParams();
        if (!$this->checkSignature($params['signature'], $params['timestamp'], $params['nonce'])) {
            return HttpHelper::buildResponse($response, []);
        }

        $response = $next($request, $response);
        return $response;
    }

    private function checkSignature($signature, $timestamp, $nonce)
    {
        $token = $_ENV['STUDENT_MINIPRO_APP_TOKEN'];
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature ) {
            return true;
        } else {
            return false;
        }
    }
}