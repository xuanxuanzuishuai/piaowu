<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/4
 * Time: 7:42 PM
 *
 * 客户数据相关功能
 */

namespace App\Controllers\Student;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\ResponseError;
use App\Libs\Valid;
use App\Models\StudentAppModel;
use App\Models\StudentOrgModel;
use App\Services\ChannelService;
use App\Services\StudentAppService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 搜索客户
 */
class Student extends ControllerBase
{

    /**
     * 获取学员列表
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function list(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'required',
                'error_code' => 'page_is_required',
            ],
            [
                'key'        => 'count',
                'type'       => 'required',
                'error_code' => 'count_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $orgId = $this->getEmployeeOrgId();

        list($data, $total) = StudentService::selectStudentByOrg($orgId,$params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'students'    => $data,
                'total_count' => $total
            ],
        ]);
    }

    public function listByOrg(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'required',
                'error_code' => 'page_is_required',
            ],
            [
                'key'        => 'count',
                'type'       => 'required',
                'error_code' => 'count_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $orgId = $params['org_id'] ?? null;

        list($data, $total) = StudentService::selectStudentByOrg($orgId,$params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'students'    => $data,
                'total_count' => $total
            ],
        ]);
    }

    /**
     * 获取学生渠道
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function getSChannels(Request $request, Response $response, $argv)
    {
        $parent_id = $request->getParam('parent_id', 0);

        $channels = ChannelService::getChannels($parent_id);
        return $response->withJson([
            'code' => 0,
            'data' => $channels
        ]);
    }

    /**
     * 获取学生详情
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function detail(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $data = StudentService::fetchStudentDetail($params['student_id'], false);
        return $response->withJson([
            'code' => 0,
            'data' => $data
        ], 200);
    }

    /**
     * 根据学生ID查询一条详情
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function info(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'student_id',
                'type'       => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        global $orgId;
        $studentId = $params['student_id'];

        $data = StudentService::getOrgStudent($orgId, $studentId);

        return $response->withJson([
            'code' => 0,
            'data' => $data
        ]);
    }

    /**
     * 修改学生信息
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function modify(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_id',
                'type'       => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required',
            ],
            [
                'key'        => 'gender',
                'type'       => 'required',
                'error_code' => 'gender_is_required',
            ],
            [
                'key'        => 'birthday',
                'type'       => 'numeric',
                'error_code' => 'birthday_must_be_numeric',
            ]
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //不能修改手机号，防止被hack
        unset($params['mobile']);

        $studentId = $params['student_id'];
        $orgId = $this->getEmployeeOrgId();

        $entry = StudentOrgModel::getRecord([
            'org_id'     => $orgId,
            'student_id' => $studentId,
        ]);

        if(empty($entry)) {
            return $response->withJson(Valid::addErrors([],'student','student_not_exist'));
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errOrAffectRows = StudentService::updateStudentDetail($params);
        $db->rollBack();

        if(is_array($errOrAffectRows)) {
            $db->rollBack();
            return $response->withJson($errOrAffectRows);
        }

        $db->commit();

        return $response->withJson([
            'code' => 0,
            'data' => ['student_id' => $studentId]
        ]);
    }


    /**
     * 添加学生
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     * @throws \Exception
     */
    public function add(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'student_name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'student_name_length_more_then_10'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'user_mobile_is_required'
            ],
            [
                'key'        => 'mobile',
                'type'       => 'regex',
                'value'      => Constants::MOBILE_REGEX,
                'error_code' => 'mobile_format_is_error'
            ],
            [
                'key'        => 'channel_id',
                'type'       => 'numeric',
                'error_code' => 'channel_id_must_be_numeric',
            ],
            [
                'key'        => 'gender',
                'type'       => 'required',
                'error_code' => 'gender_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        //检查手机号是否已经注册（本地数据库）
        $student = StudentService::getStudentByMobile($params['mobile']);
        if (!empty($student)) {
            return $response->withJson(Valid::addErrors([], 'student_mobile', 'mobile_is_exist'), StatusCode::HTTP_OK);
        }

        $orgId = $this->getEmployeeOrgId();

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = StudentService::studentRegister($params, $orgId);
        if ($res['code'] == Valid::CODE_PARAMS_ERROR) {
            $db->rollBack();
            return $response->withJson($res, StatusCode::HTTP_OK);
        }

        $studentId = $res['student_id'];
        $errOrLastId = StudentService::bindOrg($orgId, $studentId);

        if($errOrLastId instanceof ResponseError) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([],'student',$errOrLastId->getErrorMsg()));
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['last_id' => $studentId]
        ]);
    }
}