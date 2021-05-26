<?php
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
}