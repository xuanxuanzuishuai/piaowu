<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/10/22
 * Time: 4:49 PM
 */

namespace App\Controllers\ReferralMiniapp;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\Valid;
use App\Models\UserWeixinModel;
use App\Services\PayServices;
use App\Services\StudentService;
use App\Services\WeChatService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Pay extends ControllerBase
{
    public function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'uuid',
                'type'       => 'required',
                'error_code' => 'uuid_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $student = StudentService::getByUuid($params['uuid']);

        // pkg=1或者不传 代表49元的课包  pkg=2 代表9.9元的小课包
        if (!empty($params['pkg']) && $params['pkg'] == 2) {
            $packageId = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'mini_package_id');
        } else {
            $packageId = DictConstants::get(DictConstants::WEB_STUDENT_CONFIG, 'package_id');
        }
        if (PayServices::isTrialPackage($packageId) && PayServices::hasTrialed($student['id'])) {
            SimpleLogger::error('has_trialed', ['student_id' => $student['id']]);
            $ret = Valid::addAppErrors([], 'has_trialed');
            return $response->withJson($ret, StatusCode::HTTP_OK);
        }

        $extendedParams = [];

        // 微信支付，用code换取支付用公众号的open_id
        if (empty($params['open_id']) && $params['pay_channel'] == PayServices::PAY_CHANNEL_PUB) {
            if (empty($params['wx_code'])) {
                return $response->withJson(Valid::addAppErrors([], 'need_wx_code'));
            }
            $data = WeChatService::getWeixnUserOpenIDAndAccessTokenByCode($params['wx_code'], 2, UserWeixinModel::USER_TYPE_STUDENT);
            if (empty($data) || empty($data['openid'])) {
                return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'));
            }
            $extendedParams['open_id'] = $data['openid'];
        } else {
            $extendedParams['open_id'] = $params['open_id'];
        }

        $studentAddressId = $params['student_address_id'] ?? 0;
        $employeeUuid     = !empty($params['employee_id']) ? RC4::decrypt($_ENV['COOKIE_SECURITY_KEY'], $params['employee_id']) : null;

        // pay_channel 1 支付宝 2 微信H5 21 微信公众号
        $ret = PayServices::weixinCreateBill(
            $student['id'],
            $packageId,
            $params['pay_channel'],
            $_SERVER['HTTP_X_REAL_IP'],
            $studentAddressId,
            $extendedParams['open_id'],
            $employeeUuid,
            true
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'create_bill_error');
        }
        return $response->withJson($ret, StatusCode::HTTP_OK);
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
                'bill_status' => $status
            ]
        ], StatusCode::HTTP_OK);
    }
}