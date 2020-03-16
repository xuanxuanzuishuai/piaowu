<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/02/29
 * Time: 4:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\EmployeeModel;
use App\Services\DictService;
use App\Services\CollectionService;
use App\Services\EmployeeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;
use App\Models\CollectionModel;

/**
 * 学生集合控制器
 * Class Collection
 * @package App\Controllers\OrgWeb
 */
class Collection extends ControllerBase
{
    /**
     * 添加学生集合信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'collection_name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthmax',
                'value' => 50,
                'error_code' => 'collection_name_length_max'
            ],
            [
                'key' => 'assistant_id',
                'type' => 'required',
                'error_code' => 'assistant_id_is_required'
            ],
            [
                'key' => 'wechat_number',
                'type' => 'required',
                'error_code' => 'wechat_number_is_required'
            ],
            [
                'key' => 'wechat_qr',
                'type' => 'required',
                'error_code' => 'wechat_qr_is_required'
            ],
            [
                'key' => 'prepare_start_time',
                'type' => 'required',
                'error_code' => 'prepare_start_time_is_required'
            ],
            [
                'key' => 'prepare_end_time',
                'type' => 'required',
                'error_code' => 'prepare_end_time_is_required'
            ],
            [
                'key' => 'teaching_start_time',
                'type' => 'required',
                'error_code' => 'teaching_start_time_is_required'
            ],
            [
                'key' => 'teaching_end_time',
                'type' => 'required',
                'error_code' => 'teaching_end_time_is_required'
            ],
            [
                'key' => 'capacity',
                'type' => 'required',
                'error_code' => 'collection_capacity_is_required'
            ],
            [
                'key' => 'capacity',
                'type' => 'max',
                'value' => CollectionModel::COLLECTION_MAX_CAPACITY,
                'error_code' => 'collection_capacity_is_max'
            ],
            [
                'key' => 'capacity',
                'type' => 'min',
                'value' => 1,
                'error_code' => 'collection_capacity_is_not_inter'
            ],
            [
                'key' => 'teaching_type',
                'type' => 'required',
                'error_code' => 'collection_teaching_type_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        //组合数据
        $time = time();
        $collectionData = [
            'name' => $params['name'],
            'assistant_id' => $params['assistant_id'],
            'capacity' => $params['capacity'],
            'remark' => $params['remark'],
            'prepare_start_time' => $params['prepare_start_time'],
            'prepare_end_time' => Util::getStartEndTimestamp($params['prepare_end_time'])[1],
            'teaching_start_time' => $params['teaching_start_time'],
            'teaching_end_time' => Util::getStartEndTimestamp($params['teaching_end_time'])[1],
            'wechat_qr' => $params['wechat_qr'],
            'wechat_number' => $params['wechat_number'],
            'create_uid' => self::getEmployeeId(),
            'create_time' => $time,
            'type' => $params['type'] ?? CollectionModel::COLLECTION_TYPE_NORMAL,
            'teaching_type' => $params['teaching_type'],
        ];
        //写入数据
        $collectionId = CollectionService::addStudentCollection($collectionData);
        if (empty($collectionId)) {
            return $response->withJson(Valid::addErrors([], 'collection', 'add_student_collection_fail'));
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['id' => $collectionId]
        ], StatusCode::HTTP_OK);
    }


    /**
     * 学生集合详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'student_collection_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = CollectionService::getStudentCollectionDetailByID($params['id']);
        return $response->withJson([
            'code' => 0,
            'data' => ['list' => $data]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 修改学生集合信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function modify(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'student_collection_id_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        $params['uid'] = self::getEmployeeId();
        //写入数据
        $collectionId = CollectionService::updateStudentCollection($params['id'], $params);
        if (empty($collectionId)) {
            return $response->withJson(Valid::addErrors([], 'collection', 'update_student_collection_fail'));
        }
        return $response->withJson([
            'code' => 0,
            'data' => ['id' => $collectionId]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        //接收数据
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        //助教只可查看自己的班级
        $params['assistant_id'] = self::getEmployeeId();
        //获取数据
        list($count, $list) = CollectionService::getStudentCollectionList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $count,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取助教列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getAssistantList(Request $request, Response $response)
    {
        //获取助教角色ID
        $assistantRoleId = DictService::getKeyValue(Constants::DICT_TYPE_ROLE_ID, Constants::DICT_KEY_CODE_ASSISTANT);
        list($users, $totalCount) = EmployeeService::getEmployeeService("-1", 0, ['role_id' => $assistantRoleId, 'status' => EmployeeModel::STATUS_NORMAL]);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => ['list' => $users]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 全部集合数据列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function totalList(Request $request, Response $response)
    {
        //接收数据
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        //获取数据
        list($count, $list) = CollectionService::getStudentCollectionList($params, $params['page'], $params['count']);
        return $response->withJson([
            'code' => 0,
            'data' => [
                'count' => $count,
                'list' => $list
            ]
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取班级下拉菜单列表数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCollectionDropDownList(Request $request, Response $response)
    {
        $name = $request->getParam('name');
        $notOver = $request->getParam('not_over');
        $collections = CollectionService::getCollectionDropDownList($name, $notOver);
        return $response->withJson([
            'code' => 0,
            'data' => $collections
        ], StatusCode::HTTP_OK);
    }

    /**
     * 获取购买产品包下拉框
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCollectionPackageList(Request $request, Response $response)
    {
        //接收数据
        $params = $request->getParams();
        $keyCode = !empty($params['key_code']) ? $params['key_code'] : "package_id";
        //获取数据
        $list = CollectionService::getCollectionPackageList($keyCode);
        return $response->withJson([
            'code' => 0,
            'data' => ['list' => $list]
        ], StatusCode::HTTP_OK);
    }
}