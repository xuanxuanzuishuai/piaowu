<?php

namespace App\Controllers\Client\Activity;

use App\Controllers\API\Dss;
use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityClientService;
use App\Services\Activity\LimitTimeActivity\TraitService\DssService;
use Slim\Http\Request;
use Slim\Http\Response;

class LimitTimeActivityController extends ControllerBase
{
    /**
     * @param array $studentInfo
     * @return DssService
     * @throws RunTimeException
     */
    public function initServiceObj(array $studentInfo):DssService
    {
        return LimitTimeActivityClientService::getServiceObj(
            $this->ci['app_id'],
            $this->ci['from_type'],
            $studentInfo);
    }

    /**
     * 获取限时分享活动基础数据
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function baseData(Request $request, Response $response): Response
    {
        $obj = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::baseData($obj);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取参与记录
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function joinRecords(Request $request, Response $response): Response
    {
        $params = $request->getParams();
        list($page, $limit) = Util::formatPageCount($params);
        $obj = self::initServiceObj($this->ci['user_info']);
        $data = LimitTimeActivityClientService::joinRecords($obj,$page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }


}