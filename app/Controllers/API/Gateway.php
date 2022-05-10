<?php

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Services\AppTokenService;
use App\Services\WechatTokenService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Gateway extends ControllerBase
{
    /**
     * token换取uuid
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getUuid(Request $request, Response $response) {
        $rules = [
            [
                'key' => 'token',
                'type' => 'required',
                'error_code' => 'token_is_required',
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
            ],
            [
                'key' => 'client',
                'type' => 'required',
                'error_code' => 'client_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $uuid = NULL;
            if ($params['app_id'] == Constants::SMART_APP_ID) {
                switch ($params['client']) {
                    case 'app' :
                         $userInfo = AppTokenService::getTokenInfo($params['token']);
                         $uuid = DssStudentModel::getRecord(['id' => $userInfo['user_id']], ['uuid'])['uuid'];
                        break;
                    case 'wx' :
                        $userInfo = WechatTokenService::getTokenInfo($params['token']);
                        $uuid = DssStudentModel::getRecord(['id' => $userInfo['user_id']], ['uuid'])['uuid'];
                        break;
                    case 'mini_app' :
                    case 'web' :
                    default:
                        break;

                }
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['uuid' => $uuid]);
    }

    /**
     * uuid换取token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getToken(Request $request, Response $response) {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required',
            ],
            [
                'key' => 'client',
                'type' => 'required',
                'error_code' => 'client_is_required',
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $token = NULL;
            if ($params['app_id'] == Constants::SMART_APP_ID) {
                $userInfo = DssStudentModel::getRecord(['uuid' => $params['uuid']]);
                if (!empty($userInfo)) {
                    switch ($params['client']) {
                        case 'app' :
                            $token = AppTokenService::generateToken($userInfo['id'], Constants::SMART_APP_ID);
                            break;
                        case 'wx' :
                            $userWxInfo = DssUserWeiXinModel::getByUserId($userInfo['id']);
                            $token = WechatTokenService::generateToken($userInfo['id'], 1, Constants::SMART_APP_ID, $userWxInfo['open_id']);
                            break;
                        case 'mini_app' :
                        case 'web' :
                        default:
                            break;

                    }
                }
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, ['token' => $token]);
    }
}