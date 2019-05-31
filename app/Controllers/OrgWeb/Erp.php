<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/30
 * Time: 5:21 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\Boss\GiftCode;
use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Services\ErpService;
use App\Services\GiftCodeService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Erp extends ControllerBase
{
    public function exchangeGiftCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'in',
                'value' => [GiftCodeModel::BUYER_TYPE_ERP_EXCHANGE, GiftCodeModel::BUYER_TYPE_ERP_ORDER],
                'error_code' => 'exchange_type_invalid'
            ],
            [
                'key' => 'bill_id',
                'type' => 'required',
                'error_code' => 'bill_id_is_required'
            ],
            [
                'key' => 'bill_amount',
                'type' => 'required',
                'error_code' => 'bill_amount_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ret = ErpService::exchangeGiftCode([
            'uuid' => $params['uuid'],
            'mobile' => $params['mobile'],
            'name' => $params['name'],
            'gender' => $params['gender'],
            'birthday' => $params['birthday']
        ],
            $params['type'],
            (int)$params['bill_id'],
            (int)$params['bill_amount']);

        if ($ret['code'] == Valid::CODE_PARAMS_ERROR) {
            return $ret;
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $ret
            ]
        ], StatusCode::HTTP_OK);
    }
}