<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\BAService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class BA extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function baList(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $employeeId = $this->getEmployeeId();
        list($page, $count) = Util::formatPageCount($params);
        list($applyList, $totalCount) = BAService::getBAApplyList($employeeId, $params, $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'apply_list' => $applyList,
                'total_count' => $totalCount
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function exportBa(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $employeeId = $this->getEmployeeId();

        $filePath =  BAService::exportData($employeeId, $params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'file_path' => $filePath
            ]
        ], StatusCode::HTTP_OK);
    }



    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function updateApply(Request $request, Response $response, $args)
    {
        $params = $request->getParams();

        $rules = [
            [
                'key'        => 'ids',
                'type'       => 'required',
                'error_code' => 'ids_is_required'
            ],
            [
                'key'        => 'check_status',
                'type'       => 'in',
                'value'      => [2,3],
                'error_code' => 'check_status_is_error'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
             BAService::updateApply($employeeId, $params);
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
    public function getBaInfo(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'ba_id',
                'type'       => 'required',
                'error_code' => 'ba_id_is_required'
            ]
        ];

        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $info = BAService::getBaInfo($params['ba_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'ba_info' => $info
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getPassBa(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $employeeId = $this->getEmployeeId();
        list($page, $count) = Util::formatPageCount($params);
        list($applyList, $totalCount) = BAService::getPassBa($employeeId, $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'ba_list' => $applyList,
                'total_count' => $totalCount
            ]
        ], StatusCode::HTTP_OK);
    }
}