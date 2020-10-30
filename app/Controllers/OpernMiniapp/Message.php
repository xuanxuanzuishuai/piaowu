<?php
/**
 * User: lizao
 * Date: 2020.10.29 22:09
 */

namespace App\Controllers\OpernMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Services\WeChat\OpernMiniAppService;
use Slim\Http\Request;
use Slim\Http\Response;

class Message extends ControllerBase
{
    /**
     * 小程序消息回调
     * @param Request $request Request
     * @param Response $response Response
     * @return Response
     */
    public function notify(Request $request, Response $response)
    {
        $params = $request->getParams();

        $ret = OpernMiniAppService::handler($params);
        SimpleLogger::info('Opern Mini App handle message', [
            'message' => $params,
            'result' => $ret,
        ]);

        return $response->write("success");
    }
}