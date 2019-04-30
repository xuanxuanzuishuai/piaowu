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
//        $user_id = 13150;
        $result = TeacherStudentModel::getStudents($user_id);

        $data = [];
        foreach ($result as $value) {
            $org_id = $value["org_id"];
            if (array_key_exists($value["org_id"], $data))
            {
                array_push($data[$org_id]["student_list"], $value);
            } else {
                $data[$org_id] = ["student_list" => [$value], "org_id" => $org_id, "org_name" => $value["org_name"]];
            }
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => array_values($data)
        ], StatusCode::HTTP_OK);
    }

}