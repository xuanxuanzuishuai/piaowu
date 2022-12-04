<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\WeChatPackage;
use App\Services\ReceiptApplyService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Receipt extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function addReceipt(Request $request, Response $response, $args)
    {
        $params = $request->getParams();

        $rules = [
            [
                'key'        => 'receipt_number',
                'type'       => 'required',
                'error_code' => 'receipt_number_is_required'
            ],
            [
                'key'        => 'ba_id',
                'type'       => 'required',
                'error_code' => 'ba_id_is_required'
            ],
            [
                'key'        => 'buy_time',
                'type'       => 'required',
                'error_code' => 'buy_time_is_required'
            ],
            [
                'key'        => 'shop_id',
                'type'       => 'required',
                'error_code' => 'shop_id_is_required'
            ],
            [
                'key'        => 'pic_url',
                'type'       => 'required',
                'error_code' => 'pic_url_is_required'
            ],
            [
                'key'        => 'receipt_from',
                'type'       => 'in',
                'value'      => [1,2],
                'error_code' => 'receipt_from_is_error'
            ],
            [
                'key'        => 'goods_info',
                'type'       => 'required',
                'error_code' => 'goods_info_is_required'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            ReceiptApplyService::backendUploadApply($params, $employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function receiptList(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'receipt_from',
                'type'       => 'required',
                'error_code' => 'receipt_from_is_required'
            ],
            [
                'key'        => 'receipt_from',
                'type'       => 'in',
                'value'      => [1,2],
                'error_code' => 'receipt_range_is_error'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            list($page, $count) = Util::formatPageCount($params);
            list($applyList, $totalCount) = ReceiptApplyService::getReceiptList($params, $employeeId, $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'receipt_list' => $applyList,
            'total_count' => $totalCount
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function exportData(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'receipt_from',
                'type'       => 'required',
                'error_code' => 'receipt_from_is_required'
            ],
            [
                'key'        => 'receipt_from',
                'type'       => 'in',
                'value'      => [1,2],
                'error_code' => 'receipt_range_is_error'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
             $filePath =  ReceiptApplyService::exportData($params, $employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'file_path' => $filePath

        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function receiptInfo(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'receipt_id',
                'type'       => 'required',
                'error_code' => 'receipt_id_is_required'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            $info = ReceiptApplyService::getReceiptInfo($params['receipt_id'], $employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'receipt_info' => $info,
        ], StatusCode::HTTP_OK);
    }

    public function updateReceipt(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'receipt_ids',
                'type'       => 'required',
                'error_code' => 'receipt_ids_is_required'
            ],
            [
                'key'        => 'check_status',
                'type'       => 'required',
                'error_code' => 'check_status_is_required'
            ],
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            ReceiptApplyService::updateReceiptInfo($params['receipt_ids'], $params['check_status'],$employeeId);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }
}