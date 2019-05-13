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
use App\Libs\Dict;
use App\Libs\MysqlDB;
use App\Libs\ResponseError;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;
use App\Services\ChannelService;
use App\Services\StudentAccountService;
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
     * 获取学员列表(机构用)
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

        $roleId = Dict::getOrgCCRoleId();
        if(empty($roleId)) {
            return $response->withJson(Valid::addErrors([],'play_record', 'org_cc_role_is_empty_in_session'));
        }

        if($this->isOrgCC($roleId)) {
            $params['cc_id'] = $this->ci['employee']['id'];
        }

        global $orgId;

        list($data, $total) = StudentService::selectStudentByOrg($orgId, $params['page'], $params['count'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'students'    => $data,
                'total_count' => $total
            ],
        ]);
    }

    /**
     * 学生列表，内部用，可将org_id作为筛选条件
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function listByOrg(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer',
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer',
            ],
            [
                'key'        => 'is_bind',
                'type'       => 'integer',
                'error_code' => 'is_bind_is_integer',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $orgId = $params['org_id'] ?? null;

        list($data, $total) = StudentService::selectStudentByOrg($orgId, $params['page'], $params['count'], $params);

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
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;
        $studentId = $params['student_id'];

        $data = StudentService::getOrgStudent($orgId, $studentId);
        if(empty($data)) {
            return $response->withJson(Valid::addErrors([], 'student_id', 'student_not_exist'));
        }
        $data['account'] = StudentAccountService::getStudentAccount($studentId);

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
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 10,
                'error_code' => 'student_name_length_more_then_10'
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

        $studentId = $params['student_id'];
        global $orgId;

        $entry = StudentOrgModel::getRecord([
            'org_id'     => $orgId,
            'student_id' => $studentId,
        ]);

        if(empty($entry)) {
            return $response->withJson(Valid::addErrors([],'student','student_not_exist'));
        }

        $data = [
            'name'        => $params['name'],
            'gender'      => $params['gender'],
            'operator_id' => $this->getEmployeeId(),
        ];

        if(!empty($params['birthday'])) {
            $data['birthday'] = $params['birthday'];
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $errOrAffectRows = StudentService::updateStudentDetail($studentId, $data);
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
                'key'        => 'gender',
                'type'       => 'required',
                'error_code' => 'gender_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;

        $data = [
            'name'        => $params['name'],
            'mobile'      => $params['mobile'],
            'gender'      => $params['gender'],
            'channel_id'  => StudentModel::CHANNEL_BACKEND_ADD,
            'create_time' => time(),
        ];

        if(!empty($params['birthday'])) {
            $data['birthday'] = $params['birthday'];
        }

        $operatorId = $this->getEmployeeId();

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $res = StudentService::studentRegister($data, $operatorId);
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

    /**
     * 模糊查询学生，根据姓名或手机号
     * 姓名模糊匹配，手机号等于匹配
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public function fuzzySearch(Request $request, Response $response, $argv)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'key', //手机号或姓名
                'type'       => 'required',
                'error_code' => 'key_is_required'
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $roleId = Dict::getOrgCCRoleId();
        if(empty($roleId)) {
            return $response->withJson([],'play_record', 'org_cc_role_is_empty_in_session');
        }
        if(!$this->isOrgCC($roleId)) {
            $roleId = null;
        }

        $key = $params['key'];
        global $orgId;

        $records = StudentModel::fuzzySearch($orgId, $key, $roleId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $records
        ]);
    }

    /**
     * 学生账户日志
     * @param Request $request
     * @param Response $response
     * @param $argv
     * @return Response
     */
    public static function accountLog(Request $request, Response $response, $argv)
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
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($page, $count) = Util::formatPageCount($params);
        list($logs, $totalCount)  = StudentAccountService::getLogs($params['student_id'], $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'logs' => $logs,
                'total_count' => $totalCount
            ]
        ]);
    }
}