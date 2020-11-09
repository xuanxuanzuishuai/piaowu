<?php
/**
 * User: lizao
 * Date: 2020.11.09 14:00
 */

namespace App\Controllers\StaveApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\WeChat\OpernMiniAppService;
use Slim\Http\Request;
use Slim\Http\Response;

class OpernMiniapp extends ControllerBase
{
    /**
     * 获取识谱大作战小程序原始ID
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getID(Request $request, Response $response)
    {
        $data = OpernMiniAppService::getRawID();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data,
        ]);
    }
}