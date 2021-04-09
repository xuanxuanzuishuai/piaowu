<?php
/**
 * 接收erp服务相关请求
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Services\UserExchangePointsOrderService;
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
                'key' => 'exchange_points',
                'type' => 'required',
                'error_code' => 'exchange_points_is_required',
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
                'key' => 'exchange_points',
                'type' => 'integer',
                'error_code' => 'exchange_points_is_integer',
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
            $res = UserExchangePointsOrderService::toRedPack($params);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, [
            'exchange_points_red_pack_status' => $res ? 1 : 0
        ]);
    }
}