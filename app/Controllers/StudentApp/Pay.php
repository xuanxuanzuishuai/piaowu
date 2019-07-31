<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/7/30
 * Time: 4:38 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Erp;
use App\Libs\Util;
use App\Libs\Valid;
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

        $erp = new Erp();
        $ret = $erp->createBill(
            $this->ci['student']['uuid'],
            $params['package_id'],
            $params['pay_channel']
        );

        if (empty($ret)) {
            $ret = Valid::addAppErrors([], 'sys_unknown_errors');
        }

        return $response->withJson($ret, StatusCode::HTTP_OK);
    }

    public function packages(Request $request, Response $response)
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
