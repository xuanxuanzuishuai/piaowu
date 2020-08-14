<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 11:32 AM
 */

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Dict;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\RoleModel;
use App\Models\StudentModel;
use App\Services\DictService;
use App\Services\EmployeeService;
use App\Services\RoleService;
use App\Services\StudentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Employee extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function login(Request $request, Response $response, Array $args)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'login_name_is_required'
            ],
            [
                'key' => 'password',
                'type' => 'required',
                'error_code' => 'password_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $result = EmployeeService::login($params['name'], $params['password']);
        if (!empty($result['code'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($token, $userInfo) = $result;

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'token' => $token,
                'expires' => 0,
                'user' => $userInfo,
                'wsinfo' => []
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);

        list($users, $totalCount) = EmployeeService::getEmployeeService($page, $count, $params);
        $roles = RoleModel::getRoles();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'employee' => $users,
                'total_count' => $totalCount,
                'roles' => $roles,
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 机构查询名下雇员
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function listForOrg(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);
        //为下拉框用途，不能分页
        if($params['page'] == -1) {
            $page = -1;
        }

        list($users, $totalCount) = EmployeeService::getEmployeeService($page, $count, $params);
        global $orgId;
        $orgType = RoleService::getOrgTypeByOrgId($orgId);

        $roles = RoleModel::selectByOrgType($orgType);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'employee' => $users,
                'total_count' => $totalCount,
                'roles' => $roles,
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        list($user, $roles) = EmployeeService::getEmployeeDetail($params['id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'employee' => $user,
                'roles' => $roles
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 添加和编辑 雇员
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     *
     * 内部员工可以创建任意角色的员工
     * 直营可以创建角色属于直营和外部的员工，但是不能创建校长，只能校长修改校长，不能将非校长更新成校长
     * 外部只能添加属于外部的角色，但是不能创建校长，只能校长修改校长，不能将非校长更新成校长
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'role_id',
                'type'       => 'required',
                'error_code' => 'role_id_is_required'
            ],
            [
                'key'        => 'login_name',
                'type'       => 'required',
                'error_code' => 'login_name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'employee_name_is_required'
            ],
            [
                'key'        => 'org_id',
                'type'       => 'integer',
                'error_code' => 'org_id_must_be_integer'
            ],
            [
                'key'        => 'pwd',
                'type'       => 'regex',
                'value'      => '/^(?![0-9]+$)(?![a-z]+$)(?![A-Z]+$){8,20}/',
                'error_code' => 'uc_password_strength'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ],
        ];

        $params = $request->getParams();
        if(empty($params['id'])) {
            $rules[] = [
                'key'        => 'pwd',
                'type'       => 'required',
                'error_code' => 'pwd_is_required'
            ];
        }

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (!empty($params['mobile']) && !Util::isMobile($params['mobile'])) {
            $result = Valid::addErrors([], 'mobile', 'employee_mobile_format_is_error');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $role = RoleModel::getById($params['role_id']);
        if(empty($role)) {
            return $response->withJson(Valid::addErrors([],'employee','role_not_exist'));
        }


        //编辑用户时，不能修改org_id
        if(!empty($params['id'])) {
            $employee = EmployeeModel::getById($params['id']);
            if(empty($employee)) {
                return $response->withJson(Valid::addErrors([], 'employee', 'employee_not_exist'));
            }
            $params['org_id'] = $employee['org_id'];
            $params['mobile'] = $employee['mobile']; //手机号不能修改，不信任前端传递的手机号
        } else {
            //前端没有传org_id时，置为0
            $params['org_id'] = 0;

            //添加一个新员工时，检查手机号是否已经被占用
            $em = EmployeeModel::getRecord(['mobile' => $params['mobile']], []);
            if(!empty($em)) {
                return $response->withJson(Valid::addErrors([], 'employee', 'mobile_has_exist'));
            }
        }

        //前端传递的status与insertOrUpdateEmployee所需的参数一致，$params已经包含了status
        $userId = EmployeeService::insertOrUpdateEmployee($params);

        if(isset($userId['data']['errors']['login_name'])) {
            return $response->withJson(Valid::addErrors([], 'employee', 'mobile_has_exist'));
        }

        if (is_array($userId)) {
            return $response->withJson($userId);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $userId
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function setPwd(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ],
            [
                'key' => 'pwd',
                'type' => 'required',
                'error_code' => 'employee_pwd_is_required'
            ],
            [
                'key' => 'pwd',
                'type' => 'regex',
                'value' => '/^(?![0-9]+$)(?![a-z]+$)(?![A-Z]+$){6,16}/',
                'error_code' => 'uc_password_strength'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        global $orgId;
        $user = EmployeeModel::getById($params['id']);
        //检查当前用户是否有权限修改此雇员
        if($orgId > 0 && $user['org_id'] != $orgId) {
            return $response->withJson(Valid::addErrors([],'password','employee_not_belong_your_org'));
        }

        if ($user['pwd'] == md5($params['pwd'])) {
            return $response->withJson(Valid::addErrors([], 'pwd', 'employee_pwd_can_not_same'), StatusCode::HTTP_OK);
        }

        $result = EmployeeService::changePassword($params['id'], $params['pwd']);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function userSetPwd(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'pwd',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ],
            [
                'key' => 'pwd',
                'type' => 'regex',
                'value' => '/^(?![0-9]+$)(?![a-z]+$)(?![A-Z]+$){8,20}/',
                'error_code' => 'uc_password_strength'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $this->ci['employee']['id'];
        $user = EmployeeModel::getById($userId);
        if ($user['pwd'] == md5($params['pwd'])) {
            return $response->withJson(Valid::addErrors([], 'pwd', 'employee_pwd_can_not_same'), StatusCode::HTTP_OK);
        }

        $result = EmployeeService::changePassword($userId, $params['pwd']);

        return $response->withJson($result, StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getEmployeeListWithRole(Request $request, Response $response, $args){
        $rules = [
            [
                'key' => 'role_id',
                'type' => 'required',
                'error_code' => 'role_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $role_id = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, $params['role_id']);
        if(empty($role_id) && $role_id !== '0'){
            return $response->withJson(Valid::addErrors([], 'role_id','role_id_is_not_exist'), StatusCode::HTTP_OK);
        }
        $data = EmployeeService::getEmployeeListWithRole($role_id);
        return $response->withJson([
            'code'=>0,
            'data'=>$data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 批量给学生分配课管
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function assignCC(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'student_ids',
                'type'       => 'required',
                'error_code' => 'student_ids_is_required'
            ],
            [
                'key'        => 'student_ids',
                'type'       => 'array',
                'error_code' => 'student_ids_must_be_array'
            ],
            [
                'key'        => 'employee_id',
                'type'       => 'required',
                'error_code' => 'employee_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $studentIds = $params['student_ids'];
        $employeeId = $params['employee_id'];

        if(count($studentIds) == 0) {
            return $response->withJson(Valid::addErrors([], 'employee', 'student_ids_is_required'));
        }

        $roleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_CC_ROLE_ID_CODE_ORG);
        if(empty($roleId)) {
            return $response->withJson(Valid::addErrors([], 'employee', 'cc_role_not_exist'));
        }

        global $orgId;

        $employee = EmployeeModel::getRecord([
            'id'      => $employeeId,
            'org_id'  => $orgId,
            'role_id' => $roleId,
        ]);

        if(empty($employee)) {
            return $response->withJson(Valid::addErrors([], 'employee', 'cc_not_exist'));
        }

        $records = StudentModel::selectOrgStudentsIn($orgId, $studentIds);
        $ids = array_column($records, 'id');

        //检查学生是否存在，并且已经绑定机构
        foreach($studentIds as $id) {
            if(!in_array($id, $ids)) {
                return $response->withJson(Valid::addErrors([$id], 'employee', 'only_bind_student_could_distribute'));
            }
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $affectRows = StudentService::assignCC($studentIds, $orgId, $employeeId);
        if($affectRows != count($studentIds)) {
            $db->rollBack();
            return $response->withJson(Valid::addErrors([], 'employee', 'update_student_cc_fail'));
        }

        $db->commit();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['affect_rows' => $affectRows]
        ]);
    }

    public function CCList(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $ccRoleId = Dict::getOrgCCRoleId();
        if(empty($ccRoleId)) {
            return $response->withJson(Valid::addErrors([], 'cc', 'cc_role_id_not_exist'));
        }

        list($page, $count) = Util::formatPageCount([
            'page'  => $params['page'],
            'count' => $params['count']
        ]);

        if($params['page'] == -1) {
            $page = -1;
        }

        global $orgId;

        list($records, $total) = EmployeeModel::selectByRole($orgId, $ccRoleId, $page, $count);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'records'     => $records,
                'total_count' => $total,
            ]
        ]);
    }

    /**
     * 人员管理->编辑员工的信息(只有管理员有权限）
     * @param Request $request
     * @param Response $response
     * @return array|Response
     */
    public function externalInformation(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'wx_nick',
                'type'       => 'required',
                'error_code' => 'wx_nick_is_required'
            ],
            [
                'key'        => 'wx_thumb',
                'type'       => 'required',
                'error_code' => 'wx_thumb_is_required'
            ],
            [
                'key'        => 'wx_qr',
                'type'       => 'required',
                'error_code' => 'wx_qr_is_required'
            ],
            [
                'key'        => 'id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            EmployeeService::externalInformation($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 个人信息里可以编辑自己的对外信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userExternalInformation(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'wx_nick',
                'type'       => 'required',
                'error_code' => 'wx_nick_is_required'
            ],
            [
                'key'        => 'wx_thumb',
                'type'       => 'required',
                'error_code' => 'wx_thumb_is_required'
            ],
            [
                'key'        => 'wx_qr',
                'type'       => 'required',
                'error_code' => 'wx_qr_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['id'] = self::getEmployeeId();
        try {
            EmployeeService::externalInformation($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 获取对外信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getExternalInformation(Request $request, Response $response)
    {
        $id = self::getEmployeeId();
        $employeeInfo = EmployeeService::getExternalInformation($id);
        $employeeInfo['wx_thumb'] = empty($employeeInfo['wx_thumb']) ? $employeeInfo['wx_thumb'] :AliOSS::signUrls($employeeInfo['wx_thumb']);
        $employeeInfo['wx_qr'] = empty($employeeInfo['wx_qr']) ? $employeeInfo['wx_qr'] :AliOSS::signUrls($employeeInfo['wx_qr']);
        return HttpHelper::buildResponse($response, $employeeInfo);
    }
}