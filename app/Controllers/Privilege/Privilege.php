<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/29
 * Time: 下午2:38
 */

namespace App\Controllers\Privilege;

use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\PrivilegeModel;
use App\Services\GroupPrivilegeService;
use App\Services\PrivilegeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Privilege extends ControllerBase
{
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($page, $count) = Util::formatPageCount($params);

        $params['name'] = $params['name'] ?? null;
        $params['uri'] = $params['uri'] ?? null;
        list($privileges, $totalCount) = PrivilegeService::getPrivilegesService($page, $count, $params['name'], $params['uri']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'privileges' => $privileges,
                'total_count' => $totalCount
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取权限详情
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'privilege_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $privilege = PrivilegeModel::getById($params['id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'privilege' => $privilege
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 权限修改、添加
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'uri',
                'type' => 'required',
                'error_code' => 'privilege_uri_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'privilege_name_is_required'
            ],
            [
                'key' => 'method',
                'type' => 'required',
                'error_code' => 'privilege_method_is_required'
            ],
            [
                'key' => 'unique_en_name',
                'type' => 'required',
                'error_code' => 'privilege_unique_en_name_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $id = GroupPrivilegeService::insertOrUpdatePrivilege($params['id'], $params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'id' => $id
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取菜单
     */
    public function menu(Request $request, Response $response, $args)
    {
        $menus = PrivilegeService::getMenuService();

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'menus' => $menus
            ]
        ], StatusCode::HTTP_OK);
    }

    public function employee_menu(Request $request, Response $response, $args)
    {
        $rules = [
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

        $menus = PrivilegeService::getEmployeeMenuService($params['employee_id']);

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'menus' => $menus
            ]
        ], StatusCode::HTTP_OK);
    }

}
