<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:44 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\Util;
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
            ],
            [
                'key' => 'code',
                'type' => 'AlphaNum',
                'error_code' => 'gift_code_error'
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

    public function trial(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $db = MysqlDB::getDB();
        try {
            $db->beginTransaction();
            $ret = StudentServiceForApp::trial($this->ci['student']['id']);
            $db->commit();

        } catch (RunTimeException $e) {
            $db->rollBack();
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, $ret);
    }
}