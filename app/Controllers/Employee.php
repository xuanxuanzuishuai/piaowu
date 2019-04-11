<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019/2/28
 * Time: 11:32 AM
 */

namespace App\Controllers;

use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Models\RoleModel;
use App\Services\EmployeeService;
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
        echo 'ssss';exit();
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
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'role_id',
                'type' => 'required',
                'error_code' => 'role_id_is_required'
            ],
            [
                'key' => 'login_name',
                'type' => 'required',
                'error_code' => 'login_name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'employee_name_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }


        if (!empty($params['mobile']) && !Util::isMobile($params['mobile'])) {
            $result = Valid::addErrors([], 'mobile', 'employee_mobile_format_is_error');
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

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

        $user = EmployeeModel::getById($params['id']);
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
                'value' => '/^(?![0-9]+$)(?![a-z]+$)(?![A-Z]+$){6,16}/',
                'error_code' => 'uc_password_strength'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $this->employee['id'];
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
    public function setExcludePrivilege(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $params['id'];
        $privilegeIds = $params['privilegeIds'];
        EmployeePrivilegeService::updateEmployeePrivileges($userId, $privilegeIds, EmployeePrivilegeModel::TYPE_EXCLUDE);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function setExtendPrivilege(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $userId = $params['id'];
        $privilegeIds = $params['privilegeIds'];
        EmployeePrivilegeService::updateEmployeePrivileges($userId, $privilegeIds, EmployeePrivilegeModel::TYPE_INCLUDE);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => []
        ], StatusCode::HTTP_OK);
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
}