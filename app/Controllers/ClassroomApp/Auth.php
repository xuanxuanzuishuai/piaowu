<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/14
 * Time: 下午1:57
 */

namespace App\Controllers\ClassroomApp;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Services\ClassroomAppService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class Auth extends ControllerBase
{
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'account',
                'type'       => 'required',
                'error_code' => 'account_is_required'
            ],
            [
                'key'        => 'password',
                'type'       => 'required',
                'error_code' => 'password_is_required'
            ],
            [
                'key'        => 'mac',
                'type'       => 'required',
                'error_code' => 'mac_is_required'
            ],
            [
                'key'        => 'used_offline',
                'type'       => 'required',
                'error_code' => 'used_offline_is_required'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $data = ClassroomAppService::login($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildClassroomErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildClassroomResponse($response, $data);
    }
}