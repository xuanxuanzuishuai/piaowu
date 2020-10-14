<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/8
 * Time: 3:38 PM
 */

namespace App\Controllers\OrgWeb;

use App\Libs\DictConstants;
use App\Models\EmployeeModel;
use App\Services\EmployeeService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Controllers\ControllerBase;
use App\Libs\Valid;
use Slim\Http\StatusCode;


class Employee extends ControllerBase
{
    public function getDeptMembers(Request $request, Response $response)
    {
        $params = $request->getParams();

        $roleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG, $params['role_type']);
        if (empty($params['dept_id']) || empty($roleId)) {
            $members = [];
        } else {
            $members = EmployeeModel::getRecords(
                ['dept_id' => $params['dept_id'], 'role_id' => $roleId],
                ['id', 'uuid', 'name', 'role_id', 'status', 'dept_id', 'is_leader']
            );
        }

        //返回数据
        return HttpHelper::buildResponse($response, ['members' => $members]);
    }

    /**
     * 通过部门id获取本部门以及子部门的助教成员
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getDeptAssistantMembers(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'dept_id',
                'type' => 'required',
                'error_code' => 'dept_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $members = EmployeeService::getDeptAssistantMembers($params['dept_id']);
        return HttpHelper::buildResponse($response, ['members' => $members]);
    }

    /**
     * 通过部门id获取本部门以及子部门的助教成员
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getDeptCourseManageMembers(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'dept_id',
                'type' => 'required',
                'error_code' => 'dept_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $members = EmployeeService::getDeptCourseManageMembers($params['dept_id']);
        return HttpHelper::buildResponse($response, ['members' => $members]);
    }
}