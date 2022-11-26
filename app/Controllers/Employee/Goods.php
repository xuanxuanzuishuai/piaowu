<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Services\GoodsService;


class Goods extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        try {
            $employeeId = $this->getEmployeeId();
            list($page, $count) = Util::formatPageCount($params);
            list($list, $totalCount) = GoodsService::getGoodsList($params, $employeeId, $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'goods_list' => $list,
            'total_count' => $totalCount
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function addGoods(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'goods_name',
                'type' => 'required',
                'error_code' => 'goods_name_is_required'
            ],
            [
                'key' => 'goods_number',
                'type' => 'required',
                'error_code' => 'goods_number_is_required'
            ],
            [
                'key' => 'market_price',
                'type' => 'required',
                'error_code' => 'market_price_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $employeeId = $this->getEmployeeId();
            GoodsService::addGoods($params, $employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }
}