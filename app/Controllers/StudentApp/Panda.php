<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/10
 * Time: 2:04 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\PlayRecordModel;
use App\Models\StudentModel;
use App\Models\StudentModelForApp;
use App\Models\AppVersionModel;
use App\Services\AppVersionService;
use App\Services\StudentServiceForApp;
use App\Services\UserPlayServices;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Panda extends ControllerBase
{
    public function getStudent(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $student = StudentModelForApp::getStudentInfo(null, null, $params['uuid']);

        if (empty($student)) {
            $result = Valid::addAppErrors([], 'unknown_student');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'student' => $student
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 动态演奏结束
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function aiEnd(Request $request, Response $response){
        // 验证请求参数
        $rules = [
            [
                'key' => 'data',
                'type' => 'required',
                'error_code' => 'play_data_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = $params['data'];
        $mobile = $data['mobile'];
        $student = StudentModelForApp::getStudentInfo(null, $mobile, null);

        if (empty($student)) {
            $studentId = StudentServiceForApp::studentRegister($mobile, StudentModel::CHANNEL_APP_REGISTER);
            if (empty($studentId)) {
                $result = Valid::addAppErrors([], 'student_register_fail');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $student = StudentModelForApp::getById($studentId);

            if (empty($student)) {
                $result = Valid::addAppErrors([], 'unknown_student');
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
        }

        // 插入练琴纪录表
        $params['data']['lesson_type'] = PlayRecordModel::TYPE_AI;
        $params['data']['client_type'] = PlayRecordModel::CLIENT_PANDA_MINI;
        list($errCode, $ret) = UserPlayServices::addRecord($student['id'], $params['data']);
        if (!empty($errCode)) {
            $errors = Valid::addAppErrors([], $errCode);
            return $response->withJson($errors, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code' => 0,
            'data' => $ret
        ], StatusCode::HTTP_OK);
    }

    public static function recentDetail(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $uuid = $params['uuid'];
        $student = StudentModelForApp::getStudentInfo(null, null, $uuid);
        if (empty($student)) {
            $ret = ['lessons' => [], 'days' => 0, 'lesson_count' => 0, 'token' => ''];
            return $response->withJson(['code' => 0, 'data'=>$ret], StatusCode::HTTP_OK);
        }
        $appVersion = AppVersionService::getPublishVersionCode(
            AppVersionModel::APP_TYPE_STUDENT, AppVersionService::PLAT_ID_IOS);
        $ret = UserPlayServices::pandaPlayDetail($student['id'], $appVersion);
        return $response->withJson(['code' => 0, 'data'=>$ret], StatusCode::HTTP_OK);
    }


    public static function recentPlayed(Request $request, Response $response)
    {
        // 验证请求参数
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $uuid = $params['uuid'];
        $student = StudentModelForApp::getStudentInfo(null, null, $uuid);
        if (empty($student)) {
            $ret = ['is_ai_student' => false, 'days' => 0, 'lesson_count' => 0];
            return $response->withJson(['code' => 0, 'data'=>$ret], StatusCode::HTTP_OK);
        }
        $ret = UserPlayServices::pandaPlayBrief($student['id']);
        $ret['is_ai_student'] = $student['sub_start_date'] > 0;
        return $response->withJson(['code' => 0, 'data'=>$ret], StatusCode::HTTP_OK);
    }
}