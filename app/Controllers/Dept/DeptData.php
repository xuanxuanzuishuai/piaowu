<?php
/**
 * Created by PhpStorm.
 * User: du
 * Date: 2018/11/09
 */

namespace Erp\Routers\Dept;

use Erp\Services\DeptDataService;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Erp\Libs\Valid;

/** @var App $app */
/**
 * 某部门拥有其他部门数据权限修改
 */
$app->post('/dept/dept_data/modify', function (Request $request, Response $response, $args) {
    $rules = [
        [
            'key' => 'dept_id',
            'type' => 'required',
            'error_code' => 'dept_id_is_required'
        ],
        [
            'key' => 'type',
            'type' => 'required',
            'error_code' => 'dept_data_type_is_required'
        ],
        [
            'key' => 'dept_ids',
            'type' => 'required',
            'error_code' => 'dept_ids_is_required'
        ],
    ];
    $params = $request->getParams();
    $result = Valid::validate($params, $rules);
    if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
        return $response->withJson($result, 200);
    }

    $id = DeptDataService::insertOrUpdateDeptData($params);

    return $response->withJson([
        'code' => 0,
        'data' => [
            'dept_data_id' => $id,
        ]
    ], 200);
});
