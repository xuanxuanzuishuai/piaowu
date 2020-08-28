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
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\HttpHelper;
use App\Models\EmployeeModel;
use App\Services\DictService;
use App\Services\CollectionService;
use App\Services\EmployeeService;
use App\Services\ErpReferralService;
use App\Services\StudentService;
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
                'key' => 'assistant_id',
                'type' => 'required',
                'error_code' => 'assistant_id_is_required'
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
            ],
            [
                'key' => 'event_id',
                'type' => 'integer',
                'error_code' => 'event_id_must_be_integer'
            ],
            [
                'key' => 'task_id',
                'type' => 'integer',
                'error_code' => 'task_id_must_be_integer'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $operator = $this->getEmployeeId();
            $collectionId = CollectionService::addStudentCollection($params, $operator);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }

        return HttpHelper::buildResponse($response, ['id' => $collectionId]);
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
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $operator = $this->getEmployeeId();
        $collectionId = $params['id'];
        unset($params['id']);

        try {
            CollectionService::updateStudentCollection($collectionId, $params, $operator);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, ['id' => $collectionId]);
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
        $params = $request->getParams();

        // 按指定员工ID筛选时清空组ID
        if (!empty($params['assistant_id'])) {
            unset($params['dept_id']);
        }

        $assistantRoleId = DictConstants::get(DictConstants::ORG_WEB_CONFIG, 'assistant_role');

        $employee = $this->ci['employee'];

        if ($employee['role_id'] == $assistantRoleId) {
            $employeeType = 'assistant_id';
        } else {
            $employeeType = null;
        }

        if (!empty($employeeType)) { // 助教或课管查询
            if ($employee['is_leader']) { // 组长查询
                $memberIds = StudentService::getLeaderPrivilegeMemberId(
                    $employee['dept_id'],
                    $employee['id'],
                    $params['dept_id'] ?? null,
                    $params[$employeeType] ?? null
                );

                if (!empty($memberIds)) {
                    $privilegeParams[$employeeType] = $memberIds;
                } else {
                    $privilegeParams = ['assistant_id' => -1];
                }
            } else {
                $privilegeParams[$employeeType] = [$employee['id']];
            }

        } else { // 其他角色查询，可见所有
            if (!empty($params['assistant_id'])) { // 其他角色查询助教名下班级
                $privilegeParams = ['assistant_id' => $params['assistant_id']];

            } elseif (!empty($params['dept_id'])) { // 其他角色按组查询
                $privilegeParams = StudentService::getDeptPrivilege($params['dept_id']);
                if (empty($privilegeParams)) {
                    $privilegeParams = ['assistant_id' => -1];
                }
            }
        }

        $params['assistant_id'] = $privilegeParams['assistant_id'] ?? null;

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


    /**
     * 班级分配助教
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function reAllotCollectionAssistant(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'collection_id',
                'type' => 'required',
                'error_code' => 'collection_id_is_required'
            ],
            [
                'key' => 'assistant_id',
                'type' => 'required',
                'error_code' => 'assistant_id_is_required'
            ],
            [
                'key' => 'assistant_id',
                'type' => 'integer',
                'error_code' => 'assistant_id_must_be_integer'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }
        $collectionIDList = explode(',', $params['collection_id']);
        //班级分配助教
        $data = CollectionService::reAllotCollectionAssistant($collectionIDList, $params['assistant_id'], self::getEmployeeId());
        if (!empty($data)) {
            return $response->withJson($data);
        }
        return $response->withJson(Valid::formatSuccess($data));
    }

    /**
     * 获取事件任务
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getEventTasksList(Request $request, Response $response)
    {
        $data = ErpReferralService::getEventTasksList();
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }


    /**
     * 获取班级统计数据按照部门架构区分
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCollectionDeptStatisticsData(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = CollectionService::getCollectionDeptData($params);
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }
}