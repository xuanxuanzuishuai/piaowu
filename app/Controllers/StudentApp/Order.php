<?php


namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\OrderService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Order extends ControllerBase
{
    /**
     * 下单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key' => 'package_id',
                'type' => 'integer',
                'error_code' => 'package_id_must_be_integer',
            ],
            [
                'key' => 'pay_channel',
                'type' => 'integer',
                'error_code' => 'pay_channel_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $params['student_id'] = $this->ci['user_info']['user_id'];
            $data = OrderService::createAppBill($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 支付结果查询
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function billStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'order_id',
                'type'       => 'required',
                'error_code' => 'bill_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params = $request->getParams();
            $data = (new Dss())->billStatus(['bill_id' => $params['order_id']]);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['order_status' => $data['bill_status']]);
    }
}