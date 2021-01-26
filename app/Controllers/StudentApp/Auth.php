<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 8:51 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Services\AppTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{
    /**
     * 通过其他系统token得到本系统token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getTokenByOtherToken(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'token',
                'type' => 'required',
                'error_code' => 'token_is_required'
            ],
            [
                'key' => 'auth',
                'type' => 'required',
                'error_code' => 'auth_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $appId = empty($params['app_id']) ? Constants::SMART_APP_ID : $params['app_id'];
        $userId = 153;
        $token = AppTokenService::generateToken($userId, $appId);
        return HttpHelper::buildResponse($response, ['token' => $token]);
        return $response->withJson([
            'token' => $token
        ], StatusCode::HTTP_OK);
    }

    /**
     * 当前用户的信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function accountDetail(Request $request, Response $response)
    {
        $studentId = $this->ci['user_info']['user_id'];
        $data = [];
        if ($this->ci['user_info']['app_id'] == Constants::SMART_APP_ID) {
            $data = DssStudentModel::getRecord(['id' => $studentId], ['uuid']);
        }
        return HttpHelper::buildResponse($response, $data);
    }
}