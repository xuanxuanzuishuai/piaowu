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
use App\Libs\Dss;
use App\Libs\Exceptions\RunTimeException;
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
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try{
            $appId = empty($params['app_id']) ? Constants::SMART_APP_ID : $params['app_id'];
            $userId = (new Dss())->getTokenRelateUuid(['token' => $params['token']])['id'];
            if (empty($userId)) {
                throw new RunTimeException(['token_expired']);
            }
            $token = AppTokenService::generateToken($userId, $appId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
    }
        return HttpHelper::buildResponse($response, ['token' => $token]);
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