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
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
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
        //过滤login_name的前缀，比如org2_tom，前端要显示成tom
        $user['login_name'] = preg_replace('/^org\d+_(.*)/i', '${1}', $user['login_name']);
        $user['prefix'] = Util::makeOrgLoginName($user['org_id'],'');

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

        global $orgId;

        //检查非内部人员编辑的员工是否和自己在同一机构下
        if($orgId > 0 && !empty($params['id'])) {
            $employee = EmployeeModel::getById($params['id']);
            if(empty($employee)) {
                return $response->withJson(Valid::addErrors([], 'employee', 'employee_not_exist'));
            }
            if($employee['org_id'] != $orgId) {
                return $response->withJson(Valid::addErrors([], 'employee', 'can_not_edit_other_org_employee'));
            }
        }

        //直营校长
        $directPrincipalRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_DIRECT_PRINCIPAL_ROLE_ID_CODE);
        //机构校长
        $externalPrincipalRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_PRINCIPAL_ROLE_ID_CODE);
        if(empty($directPrincipalRoleId)) {
            return $response->withJson(Valid::addErrors([], 'employee', 'direct_principal_role_not_exist'));
        }
        if(empty($externalPrincipalRoleId)) {
            return $response->withJson(Valid::addErrors([], 'employee', 'external_principal_role_exist'));
        }
        //当前登录用户的roleId
        $currentRoleId = $this->ci['employee']['role_id'];
        //校长数组
        $principalRoleIds = [$directPrincipalRoleId, $externalPrincipalRoleId];

        if ($orgId > 0) {
            $roleType = RoleService::getOrgTypeByOrgId($orgId);
            //当前角色是直营，创建的角色必须是直营或外部
            if($roleType == RoleModel::ORG_TYPE_DIRECT &&
                $role['org_type'] != RoleModel::ORG_TYPE_DIRECT &&
                $role['org_type'] != RoleModel::ORG_TYPE_EXTERNAL) {
                return $response->withJson(Valid::addErrors([], 'employee', 'role_type_error'));
            }
            //当前角色是外部，创建的角色必须是外部
            if($roleType == RoleModel::ORG_TYPE_EXTERNAL && $role['org_type'] != $roleType) {
                return $response->withJson(Valid::addErrors([], 'employee', 'role_type_error2'));
            }

            //机构管理员添加雇员时，不能指定雇员所属机构，必须与添加者同属一个机构
            $params['org_id'] = $orgId;

            if(!empty($params['id'])) {
                //检查有没有不是校长角色却编辑校长的情况, 要修改的角色不能是校长，更新后的角色也不能是校长
                if(!in_array($currentRoleId, $principalRoleIds) &&
                    (in_array($params['role_id'], $principalRoleIds) || in_array($employee['role_id'], $principalRoleIds))) {
                    return $response->withJson(Valid::addErrors([], 'employee', 'has_no_privilege_edit_this_role'));
                }
                //检查校长把一个普通员工更新成校长的情况
                if(in_array($currentRoleId, $principalRoleIds) &&
                    !in_array($employee['role_id'], $principalRoleIds) &&
                    in_array($params['role_id'], $principalRoleIds)) {
                    return $response->withJson(Valid::addErrors([], 'employee', 'has_no_privilege_edit_this_role'));
                }
            } else {
                //添加员工时，要检查非内部人员都想添加校长角色的情况
                if(in_array($params['role_id'], $principalRoleIds)) {
                    return $response->withJson(Valid::addErrors([], 'employee', 'has_no_privilege_edit_this_role'));
                }
            }
        } else {
            //内部管理员添加雇员
            //角色是非内部角色时，传入的org_id不能为空
            if($role['org_type'] != RoleModel::ORG_TYPE_INTERNAL && empty($params['org_id'])) {
                return $response->withJson(Valid::addErrors([],'employee','org_can_not_be_empty'));
            }
        }

        //编辑用户时，不能修改org_id
        if(!empty($params['id'])) {
            $employee = EmployeeModel::getById($params['id']);
            if(empty($employee)) {
                return $response->withJson(Valid::addErrors([], 'employee', 'employee_not_exist'));
            }
            $params['org_id'] = $employee['org_id'];
        } else {
            //前端没有传org_id时，置为0
            $params['org_id'] = empty($params['org_id']) ? 0 : $params['org_id'];
        }

        //内部人员登录名前加org0_,机构人员登录名前加org+ID+下划线
        $params['login_name'] = Util::makeOrgLoginName($params['org_id'], $params['login_name']);

        //前端传递的status与insertOrUpdateEmployee所需的参数一致，$params已经包含了status
        $userId = EmployeeService::insertOrUpdateEmployee($params);
        if (!empty($userId) && !is_numeric($userId)) {
            return $response->withJson($userId, StatusCode::HTTP_OK);
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
}