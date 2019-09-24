<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/23
 * Time: 4:33 PM
 */

namespace App\Controllers\Classroom;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\OpernService;
use App\Services\OrganizationServiceForApp;
use App\Services\CommonServiceForApp;
use App\Services\OrganizationServiceForClassroom;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Auth extends ControllerBase
{
    /**
     * 机构教学账号登录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function login(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'account',
                'type' => 'required',
                'error_code' => 'account_is_required'
            ],
            [
                'key' => 'password',
                'type' => 'required',
                'error_code' => 'password_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $loginData = OrganizationServiceForClassroom::login($params['account'], $params['password']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildClassroomErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildClassroomResponse($response, $loginData);
    }

    /**
     * 机构教学账号登录
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function tokenLogin(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'account',
                'type' => 'required',
                'error_code' => 'account_is_required'
            ],
            [
                'key' => 'token',
                'type' => 'required',
                'error_code' => 'token_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($errorCode, $loginData) = OrganizationServiceForApp::loginWithToken($params['account'], $params['token']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $defaultCollections = OpernService::getDefaultCollections($this->ci['version']);
        $loginData['default_collections'] = $defaultCollections;

        $trialLessons = OpernService::getTrialLessonId();
        $loginData['trial_lessons'] = $trialLessons;

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
            CommonServiceForApp::SIGN_TEACHER_APP);
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