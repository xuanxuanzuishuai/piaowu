<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:44 PM
 */

namespace app\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Services\StudentServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Subscription extends ControllerBase
{
    public function redeemGiftCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'active_code_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        list($errorCode, $ret) = StudentServiceForApp::redeemGiftCode($params['code'], $this->ci['student']['id']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'result' => $ret
            ]
        ], StatusCode::HTTP_OK);
    }
}