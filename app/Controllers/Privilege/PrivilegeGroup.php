<?php

namespace App\Controllers\Privilege;

use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Models\GroupModel;
use App\Models\GroupPrivilegeModel;
use App\Services\GroupPrivilegeService;
use App\Services\PrivilegeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class GroupPrivilege extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $groups = GroupModel::getGroups();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
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
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'group_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $group = GroupModel::getById($params['id']);
        $groupPrivileges = GroupPrivilegeModel::getPrivilegesByGroupId($params['id']);
        list($privileges, $pCount) = PrivilegeService::getPrivilegesService();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'group' => $group,
                'groupPrivileges' => $groupPrivileges,
                'privileges' => $privileges,
                'pCount' => $pCount
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
                'key' => 'privilege_ids',
                'type' => 'required',
                'error_code' => 'privilege_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'group_name_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        if (empty($params['privilege_ids']) || !is_array($params['privilege_ids'])) {
            return $response->withJson(Valid::addErrors([], 'privilege_ids', 'privilege_ids_must_be_array'), StatusCode::HTTP_OK);
        }

        $groupId = GroupPrivilegeService::insertOrUpdateGroup($params['id'], $params);
        if (!empty($groupId)) {
            GroupPrivilegeModel::updateGroupPrivilege($groupId, $params['privilege_ids']);
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'groupId' => $groupId
            ]
        ], StatusCode::HTTP_OK);
    }

}
