<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/6/22
 * Time: 7:48 PM
 */

namespace App\Controllers\AIPlayMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;

class Auth extends ControllerBase
{
    /**
     * 测评请求token验证
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function verifyToken(Request $request, Response $response)
    {
        $token = $request->getHeaderLine('token')?? '';
        $userId = '';
        if (empty($token)) {
            return HttpHelper::buildResponse($response, ['user_id' => $userId]);
        }
        $data = WechatTokenService::getTokenInfo($token);
        $userId = $data['open_id'] ?? '';
        if (!empty($userId)) {
            $userId = md5($userId);
        }
        return HttpHelper::buildResponse($response, ['user_id' => $userId]);
    }
}
