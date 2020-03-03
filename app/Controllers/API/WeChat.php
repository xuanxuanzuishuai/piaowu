<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/3/3
 * Time: 2:50 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Services\WeChat\studentMiniProService;
use Slim\Http\Request;
use Slim\Http\Response;


class WeChat extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function studentMiniPro(Request $request, Response $response)
    {
        $params = $request->getParams();

        $ret = studentMiniProService::handler($params);
        SimpleLogger::info('studentMiniPro handle message', [
            'message' => $params,
            'result' => $ret,
        ]);

        return $response->write("success");
    }
}