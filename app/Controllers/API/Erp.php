<?php
/**
 * 接收erp服务相关请求
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\UserPointsExchangeOrderService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Erp extends ControllerBase
{
    /**
     * 积分兑换红包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function integralExchangeRedPack(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'points_exchange',
                'type' => 'required',
                'error_code' => 'points_exchange_is_required',
            ],
            [
                'key' => 'red_amounts',
                'type' => 'required',
                'error_code' => 'red_amounts_is_required',
            ],
            [
                'key' => 'sign',
                'type' => 'required',
                'error_code' => 'sign_is_required',
            ],
            [
                'key' => 'points_exchange',
                'type' => 'integer',
                'error_code' => 'points_exchange_is_integer',
            ],
            [
                'key' => 'red_amounts',
                'type' => 'integer',
                'error_code' => 'red_amounts_is_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $res = UserPointsExchangeOrderService::toRedPack($params);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 获取待发放金叶子积分明细
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function goldLeafList(Request $request, Response $response) {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $res = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList($params, $page, $limit);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }
}