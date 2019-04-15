<?php
/**
 * Created by PhpStorm.
 * User: liliang
 * Date: 18/7/19
 * Time: 上午11:30
 */

namespace App\Routers\Dept;

use App\Controllers\ControllerBase;
use App\Libs\Dict;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\DeptDataModel;
use App\Services\DeptService;
use App\Services\UserService;
use Slim\Http\Request;
use Slim\Http\Response;

class Dept extends ControllerBase
{

    /**
     *部门树
     */
    public function tree(Request $request, Response $response, $args)
    {
        // 所有部门
        $tree = DeptService::getDeptTree();

        return $response->withJson([
            'code' => 0,
            'data' => [
                'tree' => $tree,
            ]
        ], 200);
    }

    /**
     * 部门列表
     */
    public function list(Request $request, Response $response, $args)
    {
        $deptName = $request->getParam('dept_name', '');
        $page = !empty($params['page']) ? $params['page'] : 1;
        $count = !empty($params['count']) ? $params['count'] : Dict::getDefaultPageLimit();
        // 所有部门
        list($totalCount, $tree) = DeptService::getDeptListService($deptName, $page, $count);

        return $response->withJson([
            'code' => 0,
            'data' => [
                'tree' => $tree,
                'total_count' => $totalCount
            ]
        ], 200);
    }

    /**
     * 修改部门信息
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'dept_name',
                'type' => 'required',
                'error_code' => 'dept_name_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, 200);
        }

        $db = MysqlDB::getDB();
        $db->beginTransaction();

        $deptId = DeptService::insertOrUpdateDept($params);
        if (empty($params['id']) && !empty($deptId)) {
            // 添加数据权限部门
            DeptDataModel::insertDeptData([
                'dept_id' => $deptId,
                'data_type' => DeptDataModel::DATA_LEADS,
                'dept_ids' => $deptId,
                'create_time' => time()
            ]);
        }
        $db->commit();
        return $response->withJson([
            'code' => 0,
            'data' => [
                'deptId' => $deptId,
            ]
        ], 200);
    }

    /**
     * 部门成员
     */
    public function dept_users(Request $request, Response $response, $args)
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
            return $response->withJson($result, 200);
        }

        $list = UserService::getDeptUsers($params['dept_id']);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => [
                'list' => $list
            ]
        ], 200);
    }
}