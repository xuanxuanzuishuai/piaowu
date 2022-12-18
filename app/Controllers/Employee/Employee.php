<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Services\EmployeeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;

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
                'key' => 'login_name',
                'type' => 'required',
                'error_code' => 'login_name_is_required'
            ],
            [
                'key' => 'login_pwd',
                'type' => 'required',
                'error_code' => 'password_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $result = EmployeeService::login($params['login_name'], $params['login_pwd']);
        if (!empty($result['code'])) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }


        list($token, $userInfo) = $result;

        setcookie("token", $token, time()+2592000, '/');

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'token' => $token,
                'user' => $userInfo,
            ]
        ], StatusCode::HTTP_OK);

    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function info(Request $request, Response $response, Array $args)
    {
        $employeeId = $this->getEmployeeId();
        $params = $request->getParams();
        if (!empty($params['employee_id'])) {
            $employeeId = $params['employee_id'];
        }

        list($info, $relateRegion) = EmployeeService::getEmployeeInfo($employeeId);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'info' => $info,
                'relate_region' => $relateRegion
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

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'employee' => $users,
                'total_count' => $totalCount,
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function add(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
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
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{

            $employeeId = $this->getEmployeeId();
            EmployeeService::addEmployee($employeeId, $params);

        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function updatePwd(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'pwd',
                'type' => 'required',
                'error_code' => 'pwd_is_required'
            ],
            [
                'key' => 'employee_id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try{

            $employeeId = $this->getEmployeeId();
            EmployeeService::updatePwd($employeeId, $params);

        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
        ], StatusCode::HTTP_OK);
    }
}