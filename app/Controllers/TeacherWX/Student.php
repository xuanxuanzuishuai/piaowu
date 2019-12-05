<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/18
 * Time: 15:55
 */

namespace App\Controllers\TeacherWX;


use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\ClassV1UserModel;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\TeacherStudentModel;

class Student extends ControllerBase
{
    /** 老师获取关联学生
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function get(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        $result = Valid::appValidate($params, []);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $user_id = $this->ci['user_info']['user_id'];
        $result = TeacherStudentModel::getStudents($user_id);
        $classes = ClassV1UserModel::selectClassesByTeacher($user_id);

        $data = [];
        //学生列表
        foreach ($result as $value) {
            $org_id = $value["org_id"];
            if (array_key_exists($value["org_id"], $data))
            {
                array_push($data[$org_id]["student_list"], $value);
            } else {
                $data[$org_id] = ["student_list" => [$value], "org_id" => $org_id, "org_name" => $value["org_name"]];
            }
        }

        //教室列表
        foreach ($classes as $value) {
            $data[$value['org_id']]['class_list'][] = $value;
            $data[$value['org_id']]['org_id'] = $value['org_id'];
            $data[$value['org_id']]['org_name'] = $value['org_name'];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => array_values($data)
        ], StatusCode::HTTP_OK);
    }

}