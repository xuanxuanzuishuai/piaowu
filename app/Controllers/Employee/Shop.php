<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ReceiptApplyService;
use App\Services\ShopService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Shop extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function shopList(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        try {
            list($page, $count) = Util::formatPageCount($params);
            list($list, $totalCount) = ShopService::getShopList($params, $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'shop_list' => $list,
            'total_count' => $totalCount
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function shopDetail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'shop_id',
                'type' => 'required',
                'error_code' => 'shop_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {

            $shopDetail = ShopService::getShopDetail($params['shop_id']);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'shop_detail' => $shopDetail
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function addShop(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'province_id',
                'type' => 'required',
                'error_code' => 'province_id_is_required'
            ],
            [
                'key' => 'city_id',
                'type' => 'required',
                'error_code' => 'city_id_is_required'
            ],
            [
                'key' => 'district_id',
                'type' => 'required',
                'error_code' => 'district_id_is_required'
            ],
            [
                'key' => 'shop_name',
                'type' => 'required',
                'error_code' => 'shop_name_is_required'
            ],
            [
                'key' => 'shop_number',
                'type' => 'required',
                'error_code' => 'shop_number_is_required'
            ],
            [
                'key' => 'detail_address',
                'type' => 'required',
                'error_code' => 'detail_address_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
             ShopService::addShop($params, $employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }
}