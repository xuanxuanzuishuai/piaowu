<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/4
 * Time: 上午11:37
 */

namespace App\Controllers\Privilege;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\GroupModel;
use App\Models\RoleModel;
use App\Services\RoleService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Role extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $roles = RoleModel::getRoles();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'roles' => $roles
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 为机构管理员使用的查询角色接口
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function listForOrg(Request $request, Response $response, $args)
    {
        global $orgId;

        //根据orgId确定要查询角色的org_type
        //内部管理人员可以查询到所有角色
        //直营只能查询到直营角色和外部角色
        //外部只能查询到外部角色
        $orgType = RoleService::getOrgTypeByOrgId($orgId);

        if($orgType == RoleModel::ORG_TYPE_DIRECT) {
            $records = RoleModel::selectByOrgType([RoleModel::ORG_TYPE_DIRECT, RoleModel::ORG_TYPE_EXTERNAL]);
        } else {
            $records = RoleModel::selectByOrgType($orgType);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'roles' => $records
            ],
        ]);
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
                'error_code' => 'role_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $role = RoleModel::getById($params['id']);
        $groupIds = explode(",", $role['group_ids']);
        $groups = GroupModel::getGroups();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'role' => $role,
                'groupIds' => $groupIds,
                'groups' => $groups
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
                'key' => 'group_ids',
                'type' => 'required',
                'error_code' => 'group_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'role_name_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['id'] = $params['id'] ?? '';
        if (!is_array($params['group_ids'])) {
            return $response->withJson(Valid::addErrors([], 'role', 'param_must_be_array'), StatusCode::HTTP_OK);
        }
        $roleId = RoleService::insertOrUpdateRole($params['id'], $params);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'roleId' => $roleId
            ]
        ], StatusCode::HTTP_OK);
    }

}