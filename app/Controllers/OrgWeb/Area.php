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
    public function countryList(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
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

    /**
     * 获取省列表：修改账户收货地址使用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressProvinceList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['country_code'] = !empty($params['country_code']) ? $params['country_code'] : '100000';
        $list = AreaService::getAreaByParentCode($params['country_code']);
        $data = [];
        if (!empty($list)) {
            foreach ($list as $value) {
                $data[] = [
                    'province_code' => $value['code'],
                    'province_name' => $value['name'],
                ];
            }
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取城市列表：修改账户收货地址使用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressCityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['province_code'] = !empty($params['province_code']) ? $params['province_code'] : '110000';
        $list = AreaService::getAreaByParentCode($params['province_code']);
        $data = [];
        if (!empty($list)) {
            foreach ($list as $value) {
                $data[] = [
                    'city_code' => $value['code'],
                    'city_name' => $value['name'],
                ];
            }
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取区/县列表：修改账户收货地址使用
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function addressDistrictList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $params['city_code'] = !empty($params['city_code']) ? $params['city_code'] : '110100';
        $list = AreaService::getAreaByParentCode($params['city_code']);
        $data = [];
        if (!empty($list)) {
            foreach ($list as $value) {
                $data[] = [
                    'district_code' => $value['code'],
                    'district_name' => $value['name'],
                ];
            }
        }

        return HttpHelper::buildResponse($response, $data);
    }
}