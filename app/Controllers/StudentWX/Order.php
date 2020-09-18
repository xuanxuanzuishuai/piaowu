<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/9/4
 * Time: 7:41 PM
 */

namespace App\Controllers\StudentWX;


use App\Controllers\ControllerBase;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\RC4;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ModelV1\ErpPackageV1Model;
use App\Models\StudentModelForApp;
use App\Services\ErpServiceV1\ErpOrderV1Service;
use App\Services\PayServices;
use App\Services\PointActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Order extends ControllerBase
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
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required',
            ],
            [
                'key' => 'package_id',
                'type' => 'integer',
                'error_code' => 'package_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $studentId = $this->ci['user_info']['user_id'];

        $student = StudentModelForApp::getById($studentId);

        $user['mobile'] = Util::hideUserMobile($student['mobile']);
        $user['uuid'] = $student['uuid'];

        $channel = ErpPackageV1Model::CHANNEL_WX;
        $package = PayServices::getPackageV1Detail($params['package_id'], $channel, $student['uuid'], ErpPackageV1Model::SALE_SHOP_AI_PLAY);
        if (!isset($package['code']) || $package['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($package, StatusCode::HTTP_OK);
        }

        // 现金账户余额
        $user['cash'] = PointActivityService::getStudentCash($student['uuid']);

        $defaultAddress = ErpOrderV1Service::getStudentDefaultAddress($student['uuid']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'package' => $package['data'],
                'student' => $user,
                'default_address' => $defaultAddress
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 创建订单
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function createOrder(Request $request, Response $response)
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

        $studentId = $this->ci['user_info']['user_id'];
        $student = StudentModelForApp::getById($studentId);

        $student = [
            'id' => $studentId,
            'uuid' => $student['uuid'],
            'open_id' => $this->ci['open_id'],
            'address_id' => $params['address_id'] ?? 0
        ];
        $employeeUuid = !empty($params['employee_id']) ? RC4::decrypt($_ENV['COOKIE_SECURITY_KEY'], $params['employee_id']) : NULL;
        $channel = ErpPackageV1Model::CHANNEL_WX;

        $ret = ErpOrderV1Service::createOrder($params['package_id'], $student, $params['pay_channel'], $params['pay_type'], $employeeUuid, $channel);


        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    /**
     * 获取订单状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orderStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $erp = new Erp();
        $order = $erp->billStatusV1($params);
        $status = 0;
        if (!empty($order['data'])) {
            $status = $order['data']['order_status'];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'order_status' => $status,
            ]
        ], StatusCode::HTTP_OK);
    }
}