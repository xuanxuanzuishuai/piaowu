<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/18
 * Time: 7:48 PM
 */

namespace App\Controllers\StudentApp;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Services\CommonServiceForApp;
use App\Services\Queue\QueueService;
use App\Services\StudentLoginService;
use App\Services\StudentServiceForApp;
use App\Services\TrackService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();

        if (empty($params['mobile']) && empty($params['code'])) {
            list($errorCode, $loginData) = StudentServiceForApp::anonymousLogin(
                null,
                $this->ci['platform'],
                $this->ci['version']
            );

        } else {
            if (empty($params['mobile'])) {
                $errorCode = 'user_mobile_is_required';
            }
            if (!empty($errorCode)) {
                $result = Valid::addAppErrors([], $errorCode);
                return $response->withJson($result, StatusCode::HTTP_OK);
            }

            $channelId = $request->getParam('channel_id', StudentModel::CHANNEL_APP_REGISTER);
            list($errorCode, $loginData) = StudentServiceForApp::login(
                $params['mobile'],
                $params['code'],
                $params['password'],
                $this->ci['platform'],
                $this->ci['version'],
                $channelId,
                $params['country_code']
            );
        }



        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $platformId = TrackService::getPlatformId($this->ci['platform']);
        $trackParams = TrackService::getPlatformParams($platformId, $params);
        if (!empty($trackParams) && !empty($loginData['id']) && $loginData['id'] > 0) {
            $trackParams['platform'] = $platformId;
            $trackData = TrackService::trackEvent(TrackService::TRACK_EVENT_REGISTER,
                $trackParams,
                $loginData['id']);
            $loginData['track_complete'] = $trackData['complete'] ? 1 : 0;
            if ($loginData['track_complete']) {
                $loginData['track_data'] = ['ad_channel' => (int)$trackData['ad_channel'], 'ad_id' => (int)$trackData['ad_id']];
            }
        }

        if (empty($loginData['track_data'])) {
            $loginData['track_data'] = TrackService::getAdChannel($loginData['id']);
        }

        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        if (in_array($reviewFlagId, $loginData['flags'])) {
            $response = $response->withHeader('app-review', 1);
        }

        $params['token'] = $loginData['token'];
        QueueService::studentLoginByApp($params);

        $loginData['probable_brush'] = StudentLoginService::getStudentBrush($loginData['id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $loginData,
        ], StatusCode::HTTP_OK);
    }

    public function tokenLogin(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
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

        if (empty($params['mobile'])) {
            if (!StudentModelForApp::isAnonymousStudentToken($params['token'])) {
                $result = Valid::addAppErrors([], 'invalid_anonymous_token');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }

            list($errorCode, $loginData) = StudentServiceForApp::anonymousLogin(
                $params['token'],
                $this->ci['platform'],
                $this->ci['version']
            );

        } else {
            list($errorCode, $loginData) = StudentServiceForApp::loginWithToken(
                $params['mobile'],
                $params['token'],
                $this->ci['platform'],
                $this->ci['version']
            );
        }

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $reviewFlagId = DictConstants::get(DictConstants::FLAG_ID, 'app_review');
        if (in_array($reviewFlagId, $loginData['flags'])) {
            $response = $response->withHeader('app-review', 1);
        }

        $loginData['probable_brush'] = StudentLoginService::getStudentBrush($loginData['id']);

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
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);

        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode = CommonServiceForApp::sendValidateCode($params['mobile'],
            CommonServiceForApp::SIGN_STUDENT_APP, $params['country_code']);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    public function updatePwd(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
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
        if (empty($params['mobile'])) {
            $errorCode = 'user_mobile_is_required';
        }
        if (empty($params['password'])) {
            $errorCode = 'password_is_required';
        }
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $errorCode= StudentServiceForApp::updatePwd($params['mobile'], $params['code'], $params['password'], $params['country_code']);
        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode[0]);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> [],
        ], StatusCode::HTTP_OK);
    }
}