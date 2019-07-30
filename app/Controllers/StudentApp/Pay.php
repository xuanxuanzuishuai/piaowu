<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:38 PM
 */

namespace App\Controllers\StudentApp;

use App\Libs\Erp;
use App\Libs\Util;
use App\Libs\Valid;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Pay
{
    public static function createBill(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'type',
                'type'       => 'required',
                'error_code' => 'type_is_required',
            ],
            [
                'key'        => 'num',
                'type'       => 'required',
                'error_code' => 'num_is_required',
            ],
            [
                'key'        => 'num',
                'type'       => 'integer',
                'error_code' => 'num_must_be_integer',
            ],
            [
                'key'        => 'student_address_id',
                'type'       => 'integer',
                'error_code' => 'student_address_id_must_be_integer',
            ],
            [
                'key'        => 'num',
                'type'       => 'min',
                'value'      => 1,
                'error_code' => 'num_must_egt_1',
            ],
            [
                'key'        => 'user_id',
                'type'       => 'required',
                'error_code' => 'user_id_is_required',
            ],
            [
                'key'        => 'object_id',
                'type'       => 'required',
                'error_code' => 'object_is_required',
            ],
            [
                'key'        => 'user_type',
                'type'       => 'required',
                'error_code' => 'user_type_is_required',
            ],
            [
                'key'        => 'app_id',
                'type'       => 'required',
                'error_code' => 'app_id_is_required',
            ],
            [
                'key'        => 'fee_type',
                'type'       => 'required',
                'error_code' => 'fee_type_is_required',
            ],
            [
                'key'        => 'amount',
                'type'       => 'required',
                'error_code' => 'amount_is_required',
            ],
            [
                'key'        => 'oprice',
                'type'       => 'required',
                'error_code' => 'oprice_is_required',
            ],
            [
                'key'        => 'msg',
                'type'       => 'required',
                'error_code' => 'msg_is_required',
            ],
            [
                'key'        => 'pay_type',
                'type'       => 'required',
                'error_code' => 'pay_type_is_required',
            ],
            [
                'key'        => 'pay_channel',
                'type'       => 'required',
                'error_code' => 'pay_channel_is_required',
            ],
            [
                'key'        => 'object_type',
                'type'       => 'required',
                'error_code' => 'object_type_is_required',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $erp = new Erp();
        $ret = $erp->createBill($params);

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'sys_unknown_errors');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public static function packageList(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $erp = new Erp();
        $ret = $erp->packageList();

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'sys_unknown_errors');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }
}
