<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/22
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\AreaService;
use Slim\Http\Request;
use Slim\Http\Response;

class Area extends ControllerBase
{

    /**
     * 获取国家列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function countryList(/** @noinspection PhpUnusedParameterInspection */Request $request, Response $response)
    {
        $list = AreaService::countryList();
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取省列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function provinceList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::provinceList($params);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取市列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function cityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::cityList($params);
        return HttpHelper::buildResponse($response, $list);
    }

    /**
     * 获取区列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function districtList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $list = AreaService::districtList($params);
        return HttpHelper::buildResponse($response, $list);
    }
}