<?php

namespace App\Controllers\Client\Activity;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityClientService;
use Slim\Http\Request;
use Slim\Http\Response;

class LimitTimeActivityController extends ControllerBase
{
    /**
     * 获取限时分享活动基础数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function baseData(Request $request, Response $response): Response
    {
        $data = LimitTimeActivityClientService::run();
        return HttpHelper::buildResponse($response, $data);
    }
}