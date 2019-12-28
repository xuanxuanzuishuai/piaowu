<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/11/7
 * Time: 2:32 PM
 */

namespace App\Controllers\StudentWeb;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\StudentLandingModel;
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

        $extendedParams = [];

        //公众号支付，open_id是必填参数, 注册时应该已经获取到了open_id, 这里去缓存里取值就可以了
        if($params['pay_channel'] == PayServices::PAY_CHANNEL_PUB) {
            $openId = StudentLandingModel::getOpenId($params['uuid']);
            if(!empty($openId)) {
                $extendedParams['open_id'] = $openId;
            } else {
                return $response->withJson(Valid::addAppErrors([], 'can_not_obtain_open_id'));
            }
        }

        // pay_channel 1 支付宝 2 微信H5 21 微信公众号
        $ret = PayServices::webCreateBill(
            $params['uuid'],
            $params['pay_channel'],
            $_SERVER['HTTP_X_REAL_IP'],
            $extendedParams
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'web_create_bill_error');
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