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
use App\Libs\DictConstants;
use App\Libs\NewSMS;
use App\Libs\SimpleLogger;
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

        // 换购上线前已经提前发送激活码的用户
        $preSellUserMobiles = [
            '15034197693', // 35ee02rohxyc
            '18646251090', // 38w1djdqsm68
            '15958918464', // 3cdoqzzt3aqs
            '13054520890', // 3fvc4glvdzc4
            '15262307708', // 3jczhx7xonwg
            '13666632131', // 3ttxmb24kpc0
            '13995491260', // 1zilijepc98k
            '15779880088', // 1w0y52sfg2as

            '18511327550', // 线上测试账号
        ];
        if (!in_array($params['mobile'], $preSellUserMobiles)) {
            list($sign, $content) = ErpService::exchangeSMSData(implode(',', $ret));
            $sms = new NewSMS(DictConstants::get(DictConstants::SERVICE, 'sms_host'));
            $sms->send($sign, $params['mobile'], $content);
        } else {
            SimpleLogger::debug(__FILE__ . ':' . __LINE__ . ' preSellUser', [
                'uuid' => $params['uuid'],
                'mobile' => $params['mobile'],
                'gift_codes' => $ret
            ]);
        }


        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'gift_codes' => $ret
            ]
        ], StatusCode::HTTP_OK);
    }

    public static function abandonGiftCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'bill_id',
                'type' => 'required',
                'error_code' => 'bill_id_is_required'
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = ErpService::abandonGiftCode($params['bill_id'], $params['uuid']);
        if (!empty($errorCode)) {
            $result = Valid::addErrors([],'bill_id',$errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ], StatusCode::HTTP_OK);
    }
}