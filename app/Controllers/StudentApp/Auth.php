<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 7:48 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\AppVersionModel;
use App\Services\AppVersionService;
use App\Services\CommonServiceForApp;
use App\Services\StudentServiceForApp;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ],
            [
                'key' => 'code',
                'type' => 'required',
                'error_code' => 'validate_code_is_required'
            ],
            [
                'key' => 'code',
                'type' => 'regex',
                'value' => '/[0-9]{4}/',
                'error_code' => 'validate_code_error'
            ],
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($errorCode, $loginData) = StudentServiceForApp::login($params['mobile'], $params['code']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 审核版本自动激活
        if ($this->ci['platform'] == AppVersionService::PLAT_IOS) {
            $reviewVersion = AppVersionService::getReviewVersionCode(AppVersionModel::APP_TYPE_STUDENT,
                AppVersionService::getPlatformId(AppVersionService::PLAT_IOS));
            if ($reviewVersion == $this->ci['version']) {
                $loginData['sub_end_date'] = '20250101';
            }
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $loginData,
        ], StatusCode::HTTP_OK);
    }

    public function tokenLogin(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ],
            [
                'key' => 'token',
                'type' => 'required',
                'error_code' => 'user_token_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($errorCode, $loginData) = StudentServiceForApp::loginWithToken($params['mobile'], $params['token']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        // 审核版本自动激活
        if ($this->ci['platform'] == AppVersionService::PLAT_IOS) {
            $reviewVersion = AppVersionService::getReviewVersionCode(AppVersionModel::APP_TYPE_STUDENT,
                AppVersionService::getPlatformId(AppVersionService::PLAT_IOS));
            if ($reviewVersion == $this->ci['version']) {
                $loginData['sub_end_date'] = '20250101';
            }
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $loginData,
        ], StatusCode::HTTP_OK);
    }

    public function validateCode(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'regex',
                'value' => '/^[0-9]{11}$/',
                'error_code' => 'mobile_format_error'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'],
            CommonServiceForApp::SIGN_STUDENT_APP);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }
}