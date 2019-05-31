<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/11/4
 * Time: 7:42 PM
 *
 * 客户数据相关功能
 */

namespace App\Controllers\Student;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\StudentFollowUpService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 某个学生的跟进记录
 */
class FollowRemark extends ControllerBase
{

    /**
     * 给学生添加跟进记录
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
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
                'key'        => 'user_id',
                'type'       => 'required',
                'error_code' => 'user_id_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['operator_id'] = $this->ci['employee']['id'];

        StudentFollowUpService::addStudentFollowRemark($params) ;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS
        ]);
    }

    /**
     * 给学生添加跟进记录
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function lookOver(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'user_id',
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
        list($data, $total) = StudentFollowUpService::lookStudentFollowRemark($params['user_id'], $params['page'], $params['count']) ;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'follow_remark' => $data,
                'total_count' => $total
            ]
        ]);
    }
}