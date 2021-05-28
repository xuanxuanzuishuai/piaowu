<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentStorageService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AgentStorage extends ControllerBase
{
    /**
     * 退款申请
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refundAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key' => 'amount',
                'type' => 'required',
                'error_code' => 'amount_is_required'
            ],
            [
                'key' => 'amount',
                'type' => 'integer',
                'error_code' => 'amount_is_integer'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentStorageService::addRefund($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 新增代理商预存订单数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key' => 'package_amount',
                'type' => 'required',
                'error_code' => 'package_amount_is_required'
            ],
            [
                'key' => 'package_amount',
                'type' => 'max',
                'value' => 200,
                'error_code' => 'package_amount_between_1_200'
            ],
            [
                'key' => 'package_amount',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'package_amount_between_1_200'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'required',
                'error_code' => 'package_unit_price_is_required'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'max',
                'value' => 2000,
                'error_code' => 'package_unit_price_between_1_2000'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'package_unit_price_between_1_2000'
            ],
            [
                'key' => 'payment_serial_number',
                'type' => 'required',
                'error_code' => 'payment_serial_number_is_required'
            ],
            [
                'key' => 'payment_mode',
                'type' => 'required',
                'error_code' => 'payment_mode_is_required'
            ],
            [
                'key' => 'payment_mode',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'payment_mode_error'
            ],
            [
                'key' => 'payment_time',
                'type' => 'required',
                'error_code' => 'payment_time_is_required'
            ],
            [
                'key' => 'payment_screen_shot',
                'type' => 'required',
                'error_code' => 'payment_screen_shot_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AgentStorageService::addAgentPreStorage($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 编辑退款申请
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refundUpdate(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'refund_id',
                'type' => 'required',
                'error_code' => 'refund_id_is_required'
            ],
            [
                'key' => 'amount',
                'type' => 'required',
                'error_code' => 'amount_is_required'
            ],
            [
                'key' => 'amount',
                'type' => 'integer',
                'error_code' => 'amount_is_integer'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentStorageService::updateRefund($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 编辑代理商预存订单数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'storage_id',
                'type' => 'required',
                'error_code' => 'agent_storage_id_is_required'
            ],
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key' => 'package_amount',
                'type' => 'required',
                'error_code' => 'package_amount_is_required'
            ],
            [
                'key' => 'package_amount',
                'type' => 'max',
                'value' => 200,
                'error_code' => 'package_amount_between_1_200'
            ],
            [
                'key' => 'package_amount',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'package_amount_between_1_200'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'required',
                'error_code' => 'package_unit_price_is_required'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'max',
                'value' => 2000,
                'error_code' => 'package_unit_price_between_1_2000'
            ],
            [
                'key' => 'package_unit_price',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'package_unit_price_between_1_2000'
            ],
            [
                'key' => 'payment_serial_number',
                'type' => 'required',
                'error_code' => 'payment_serial_number_is_required'
            ],
            [
                'key' => 'payment_mode',
                'type' => 'required',
                'error_code' => 'payment_mode_is_required'
            ],
            [
                'key' => 'payment_mode',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'payment_mode_error'
            ],
            [
                'key' => 'payment_time',
                'type' => 'required',
                'error_code' => 'payment_time_is_required'
            ],
            [
                'key' => 'payment_screen_shot',
                'type' => 'required',
                'error_code' => 'payment_screen_shot_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AgentStorageService::updateAgentPreStorage($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 代理商退款列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refundList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['only_read_self'] = self::getEmployeeDataPermission();
        $data = AgentStorageService::listRefund($params, self::getEmployeeId());
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 代理商账户预存订单数据详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'storage_id',
                'type' => 'required',
                'error_code' => 'agent_storage_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AgentStorageService::getAgentPreStorageDetail($params['storage_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 代理商预存订单数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['only_read_self'] = self::getEmployeeDataPermission();
        $data = AgentStorageService::getAgentPreStorageList($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 代理商退款审核
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refundVerify(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'refund_id',
                'type' => 'required',
                'error_code' => 'refund_id_is_required'
            ],
            [
                'key' => 'operation',
                'type' => 'required',
                'error_code' => 'operation_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentStorageService::verifyRefund($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 审批代理商预存订单数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function approval(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'storage_id',
                'type' => 'required',
                'error_code' => 'agent_storage_id_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'in',
                'value' => [2, 3],
                'error_code' => 'status_is_invalid'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AgentStorageService::approvalAgentPreStorage($params['storage_id'], $params['status'], $params['remark'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 退款详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refundDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'refund_id',
                'type' => 'required',
                'error_code' => 'refund_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AgentStorageService::detailRefund($params['refund_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取预存年卡消费与进帐日志
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function processLog(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $logData = AgentStorageService::getAgentPreStorageProcessLog($params);
        return HttpHelper::buildResponse($response, $logData);
    }
}