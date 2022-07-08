<?php

namespace App\Controllers\Client\Activity;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Models\OperationActivityModel;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityClientService;
use App\Services\ActivityService;
use App\Services\PosterTemplateService;
use App\Services\WeekActivityService;
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
        $data = LimitTimeActivityClientService::baseData($this->ci['user_info'], $this->ci['app_id']);

        return HttpHelper::buildResponse($response, $data);
    }
}