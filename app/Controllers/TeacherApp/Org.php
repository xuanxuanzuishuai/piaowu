<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:17 PM
 */

namespace App\Controllers\TeacherApp;


use App\Libs\Valid;
use App\Services\OrganizationServiceForApp;
use App\Controllers\ControllerBase;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Org extends ControllerBase
{

    public function getStudents(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];
        $students = OrganizationServiceForApp::getStudents($orgId, $params['teacher_id']);

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> ['students' => $students],
        ], StatusCode::HTTP_OK);
    }

    public function selectStudent(Request $request, Response $response)
    {

        $params = $request->getParams();
        $rules = [
            [
                'key' => 'teacher_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ],
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'teacher_id_is_required'
            ]
        ];
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $this->ci['org']['id'];

        list($errorCode, $loginData) = OrganizationServiceForApp::teacherLogin($orgId,
            $params['account'],
            $params['teacher_id'],
            $params['student_id']
        );

        if (!empty($errorCode)) {
            $result = Valid::addAppErrors([], $errorCode);
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        return $response->withJson([
            'code'=> Valid::CODE_SUCCESS,
            'data'=> $loginData,
        ], StatusCode::HTTP_OK);
    }
}