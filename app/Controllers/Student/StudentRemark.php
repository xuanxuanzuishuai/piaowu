<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/3/20
 * Time: 1:57 PM
 */

namespace App\Controllers\Student;

use App\Controllers\ControllerBase;
use App\Services\StudentRemarkService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Valid;
use Slim\Http\StatusCode;

class StudentRemark extends ControllerBase
{

    public function add(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'remark',
                'type'       => 'required',
                'error_code' => 'remark_is_required',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'remark_status',
                'type'       => 'required',
                'error_code' => 'remark_status_is_required',
            ],
            [
                'key'        => 'images',
                'type'       => 'array',
                'error_code' => 'images_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = $this->ci['employee']['id'];
        StudentRemarkService::addRemark($params['student_id'], $params['remark_status'], $params['remark'], $params['images'], $employeeId) ;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ]);
    }

    public function remarkList(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'user_id_is_required',
            ],
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($data, $total) = StudentRemarkService::getRemarkList($params['student_id'], $params['page'], $params['count']) ;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'student_remark' => $data,
                'total_count' => $total
            ]
        ]);
    }
}