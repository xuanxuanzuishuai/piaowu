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
use App\Models\StudentModelForApp;
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

}