<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/9/25
 * Time: 6:11 PM
 */

namespace App\Controllers\ClassV1;

use App\Controllers\ControllerBase;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ClassV1Service;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


Class ClassV1 extends ControllerBase
{
    /**
     * 班级列表
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function list(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);

        list($count, $classes) = ClassV1Service::classList($params['page'], $params['count'], $params);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $count,
                'classes' => $classes
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 班级添加
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
                'error_code' => 'class_name_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $result = ClassV1Service::addClass($params['name'], $employeeId, $params['student_ids'] ?? null,
            $params['teachers'] ?? null, $params['campus_id'] ?? 0, $params['desc'] ?? null);
        if (!empty($result['code'])) {
            $db->rollBack();
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();
        return $response->withJson([
            'code' => 0,
            'data' => ['classId' => $result]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 班级修改
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function modify(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'class_name_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        $result = ClassV1Service::modifyClass($params['class_id'], $params['name'], $employeeId,
            $params['student_ids'] ?? [], $params['teachers'] ?? null,
            $params['campus_id'] ?? 0, $params['desc'] ?? null);
        if (!empty($result['code'])) {
            $db->rollBack();
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $db->commit();
        return $response->withJson([
            'code' => 0,
            'data' => ['classId' => $result]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 班级详情
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function detail(Request $request, Response $response, $args)
    {
        $rules = [
            [
                'key' => 'class_id',
                'type' => 'required',
                'error_code' => 'class_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $class = ClassV1Service::getClass($params['class_id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['class' => $class]
        ], StatusCode::HTTP_OK);
    }
}