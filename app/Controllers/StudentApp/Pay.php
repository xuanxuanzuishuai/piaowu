<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:38 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
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

        $ret = PayServices::createBill(
            $this->ci['student']['uuid'],
            $params['package_id'],
            $params['pay_channel'],
            $_SERVER['HTTP_X_REAL_IP']
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'sys_unknown_errors');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function packages(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $packages = PayServices::getPackages();

        return $response->withJson([
            'code' => 0,
            'data' => [
                'packages' => $packages,
            ]
        ], StatusCode::HTTP_OK);
    }
}
