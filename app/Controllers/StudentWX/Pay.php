<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/3/30
 * Time: 6:36 PM
 */

namespace App\Controllers\StudentWX;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\StudentModelForApp;
use App\Services\PayServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Pay extends ControllerBase
{
    /**
     * 获取课包详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPackageDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModelForApp::getById($studentId);

        $package = PayServices::getPackageDetail($params['package_id'], $studentId);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'package' => $package,
                'student' => $student
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 创建订单
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['user_info']['user_id'];

        $ret = PayServices::weixinCreateBill(
            $studentId,
            $params['package_id'],
            $params['pay_channel'],
            $_SERVER['HTTP_X_REAL_IP'],
            $params['student_address_id'] ?? 0
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'sys_unknown_errors');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    /**
     * 获取订单状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function billStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'bill_id',
                'type'       => 'required',
                'error_code' => 'bill_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $status = PayServices::getBillStatus($params['bill_id']);
        // $status 可能为 '0', 要用全等
        if ($status === null) {
            $result = Valid::addAppErrors([], 'bill_not_exist');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => 0,
            'data' => [
                'bill_status' => $status,
            ]
        ], StatusCode::HTTP_OK);
    }
}