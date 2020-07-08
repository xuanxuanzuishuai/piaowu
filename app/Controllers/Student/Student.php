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
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\ResponseError;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\StudentModel;
use App\Models\StudentOrgModel;
use App\Services\ChannelService;
use App\Services\DictService;
use App\Services\FlagsService;
use App\Services\StudentAccountService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\HttpHelper;

/**
 * 搜索客户
 */
class Student extends ControllerBase
{

    /**
     * 获取学员列表(机构用)
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
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
     * @return Response
     */
    public function listByOrg(Request $request, Response $response)
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

        //过滤条件
        //id, org_id, channel_id, start_create_time, end_create_time,
        //sub_status, sub_start_date, sub_end_date, gender
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
     * @return Response
     */
    public function getSChannels(Request $request, Response $response)
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
     * @return Response
     */
    public function info(Request $request, Response $response)
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
        if (empty($orgId)) {
            $data['flags'] = FlagsService::flagsToArray($data['flags']);
        } else {
            unset($data['flags']);
        }

        if(empty($data)) {
            return $response->withJson(Valid::addErrors([], 'student_id', 'student_not_exist'));
        }
        $data['account'] = StudentAccountService::getStudentAccounts($studentId, $orgId);

        return $response->withJson([
            'code' => 0,
            'data' => $data,
        ]);
    }

    /**
     * 修改学生信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function modify(Request $request, Response $response)
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
     * @return Response
     */
    public function add(Request $request, Response $response)
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
            'channel_id'  => StudentModel::CHANNEL_BACKEND_ADD
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

        // 学生和机构已经绑定，并且已有CC
        $studentOrg = StudentOrgModel::getRecord(['org_id' => $orgId, 'student_id' => $studentId, 'status' => StudentOrgModel::STATUS_NORMAL]);
        if (!empty($studentOrg['cc_id'])) {
            $db->commit();
            return $response->withJson(Valid::addErrors([], 'student_id', 'student_has_bind_other_cc'));
        }

        // 绑定机构
        $errOrLastId = StudentService::bindOrg($orgId, $studentId);

        if($errOrLastId instanceof ResponseError) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([],'student',$errOrLastId->getErrorMsg()));
        }

        // 将该学生分配给CC
        $roleId = Dict::getOrgCCRoleId();
        if (empty($roleId)) {
            return $response->withJson(Valid::addErrors([],'cc_id', 'org_cc_role_is_empty_in_session'));
        }
        if($this->isOrgCC($roleId)) {
            StudentService::assignCC($studentId, $orgId, $operatorId);
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
     * @return Response
     */
    public function fuzzySearch(Request $request, Response $response)
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
     * @return Response
     */
    public static function accountLog(Request $request, Response $response)
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
            ],
            [
                'key'        => 'org_id',
                'type'       => 'required',
                'error_code' => 'org_id_is_required'
            ],
            [
                'key'        => 'org_id',
                'type'       => 'integer',
                'error_code' => 'org_id_is_integer'
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($page, $count) = Util::formatPageCount($params);
        list($logs, $totalCount)  = StudentAccountService::getLogs($params['student_id'], $params['org_id'], $page, $count);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'logs' => $logs,
                'total_count' => $totalCount
            ]
        ]);
    }

    /**
     * 根据学生ID获取详情数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
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
        $data = StudentService::getStudentDetail($params['student_id']);
        if (empty($data)) {
            $errorResult = Valid::addErrors([], 'student_id', 'student_not_exist');
            return HttpHelper::buildOrgWebErrorResponse($response, $errorResult['data']['errors']);
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 更新学生添加助教微信状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAddAssistantStatus(Request $request, Response $response)
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
            ],
            [
                'key'        => 'add_assistant_status',
                'type'       => 'required',
                'error_code' => 'add_assistant_status_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = StudentService::updateAddAssistantStatus($params['student_id'], $params['add_assistant_status']);
        if (empty($res)) {
            $errorResult = Valid::addErrors([], 'student', 'update_date_failed');
            return HttpHelper::buildOrgWebErrorResponse($response, $errorResult['data']['errors']);
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取学生列表数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function searchList(Request $request, Response $response)
    {
        $params = $request->getParams();

        // 助教和课管不允许同时筛选
        if (!empty($params['assistant_id']) && !empty($params['course_manage_id'])) {
            return HttpHelper::buildErrorResponse($response,
                RunTimeException::makeAppErrorData('class_id_student_id_only_one_not_empty'));
        }

        // 按指定员工ID筛选时清空组ID
        if (!empty($params['assistant_id']) || !empty($params['course_manage_id'])) {
            unset($params['dept_id']);
        }
        
        list($assistantRoleId, $courseManageRoleId) = DictConstants::getValues(DictConstants::ORG_WEB_CONFIG,
            ['assistant_role', 'course_manage_role']);

        $employee = $this->ci['employee'];

        if ($employee['role_id'] == $assistantRoleId) {
            $employeeType = 'assistant_id';
        } elseif ($employee['role_id'] == $courseManageRoleId) {
            $employeeType = 'course_manage_id';
        } else {
            $employeeType = null;
        }

        if (!empty($employeeType)) { // 助教或课管查询
            if ($employee['is_leader']) { // 组长查询
                $memberIds = StudentService::getLeaderPrivilegeMemberId(
                    $employee['dept_id'],
                    $employee['id'],
                    $params['dept_id'] ?? null,
                    $params[$employeeType] ?? null
                );

                if (!empty($memberIds)) {
                    $privilegeParams[$employeeType] = $memberIds;
                } else {
                    $privilegeParams = ['assistant_id' => -1, 'course_manage_id' => -1];
                }
            } else {
                $privilegeParams[$employeeType] = [$employee['id']];
            }

        } else { // 其他角色查询，可见所有
            if (!empty($params['assistant_id'])) { // 其他角色查询助教名下学员
                $privilegeParams = ['assistant_id' => $params['assistant_id']];

            } elseif (!empty($params['course_manage_id'])) { // 其他角色查询课管名下学员
                $privilegeParams = ['course_manage_id' => $params['course_manage_id']];

            } elseif (!empty($params['dept_id'])) { // 其他角色按组查询
                $privilegeParams = StudentService::getDeptPrivilege($params['dept_id']);
                if (empty($privilegeParams)) {
                    $privilegeParams = ['assistant_id' => -1, 'course_manage_id' => -1];
                }
            }
        }

        $params['assistant_id'] = $privilegeParams['assistant_id'] ?? null;
        $params['course_manage_id'] = $privilegeParams['course_manage_id'] ?? null;

        list($page, $count) = Util::formatPageCount($params);

        $res = StudentService::searchList($params, $page, $count);

        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 学生分配班级
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function allotCollection(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_ids',
                'type'       => 'required',
                'error_code' => 'student_ids_is_required',
            ],
            [
                'key'        => 'student_ids',
                'type'       => 'array',
                'error_code' => 'student_id_must_be_array'
            ],
            [
                'key'        => 'collection_id',
                'type'       => 'required',
                'error_code' => 'collection_id_is_required',
            ],
            [
                'key'        => 'collection_id',
                'type'       => 'integer',
                'error_code' => 'collection_id_must_be_integer'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = $this->getEmployeeId();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = StudentService::allotCollection($params['student_ids'], $params['collection_id'], $employeeId);
        if($res['code'] != Valid::CODE_SUCCESS){
            $db->rollBack();
        }else{
            $db->commit();
        }
        return $response->withJson($res);
    }

    /**
     * 学生分配助教
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function allotAssistant(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key'        => 'student_ids',
                'type'       => 'required',
                'error_code' => 'student_ids_is_required',
            ],
            [
                'key'        => 'student_ids',
                'type'       => 'array',
                'error_code' => 'student_id_must_be_array'
            ],
            [
                'key'        => 'assistant_id',
                'type'       => 'required',
                'error_code' => 'assistant_id_is_required',
            ],
            [
                'key'        => 'assistant_id',
                'type'       => 'integer',
                'error_code' => 'assistant_id_must_be_integer'
            ],
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = $this->getEmployeeId();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $res = StudentService::allotAssistant($params['student_ids'], $params['assistant_id'], $employeeId);
        if($res['code'] != Valid::CODE_SUCCESS){
            $db->rollBack();
        }else{
            $db->commit();
        }
        return $response->withJson($res);
    }

    /**
     * 更新学生微信账号数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAddWeChatAccount(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required',
            ],
            [
                'key' => 'student_id',
                'type' => 'integer',
                'error_code' => 'student_id_must_be_integer'
            ],
            [
                'key' => 'wechat_number',
                'type' => 'required',
                'error_code' => 'wechat_number_is_required',
            ]
        ];
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        //修改数据
        $affectRow = StudentService::updateAddWeChatAccount($params['student_id'], $params['wechat_number']);
        if (empty($affectRow)) {
            $errorResult = Valid::addErrors([], 'student', 'update_date_failed');
            return HttpHelper::buildOrgWebErrorResponse($response, $errorResult['data']['errors']);
        }
        return HttpHelper::buildResponse($response, []);
    }
}