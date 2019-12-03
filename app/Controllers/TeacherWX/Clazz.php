<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/12/2
 * Time: 下午4:23
 */

namespace App\Controllers\TeacherWX;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Models\ClassV1UserModel;
use App\Services\ClassUserV1Service;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

//班级
class Clazz extends ControllerBase
{
    public function list(Request $request, Response $response)
    {
        $userId = $this->ci['user_info']['user_id'];
        $records = ClassUserV1Service::selectClassesByUser($userId, ClassV1UserModel::ROLE_TEACHER);

        return HttpHelper::buildResponse($response, $records);
    }

    public function studentList(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'class_id',
                'type'       => 'required',
                'error_code' => 'class_id_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $records = ClassUserV1Service::selectStudentsByClass($params['class_id']);

        return HttpHelper::buildResponse($response, $records);
    }
}