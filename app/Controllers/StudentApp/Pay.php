<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:38 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\GiftCodeModel;
use App\Services\PayServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Pay extends ControllerBase
{
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
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentId = $this->ci['student']['id'];
        if (PayServices::isTrialPackage($params['package_id']) && PayServices::hasTrialed($studentId)) {
            SimpleLogger::error('has_trialed', ['student_id' => $studentId]);
            $ret = Valid::addAppErrors([], 'has_trialed');
            return $response->withJson($ret, StatusCode::HTTP_OK);
        }

        $ret = PayServices::createBill(
            $this->ci['student']['uuid'],
            $params['package_id'],
            $params['pay_channel'],
            $_SERVER['HTTP_X_REAL_IP']
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'create_bill_error');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function appPackages(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $packages = PayServices::getPackages($this->ci['student']['id']);

        return $response->withJson([
            'code' => 0,
            'data' => [
                'packages' => $packages,
            ]
        ], StatusCode::HTTP_OK);
    }

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
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $status = PayServices::getBillStatus($params['bill_id']);
        if ($status == PayServices::BILL_STATUS_SUCCESS) {
            $giftCode = GiftCodeModel::getByParentBillId($params['bill_id']);
        }

        if (empty($giftCode)) {
            $giftCode = [];
        }

        // $status 可能为 '0', 要用全等
        if ($status === null) {
            $result = Valid::addAppErrors([], 'bill_not_exist');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => 0,
            'data' => [
                'bill_status' => $status,
                'gift_code' => $giftCode,
            ]
        ], StatusCode::HTTP_OK);
    }
}
