<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/22
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AgentOperationLogModel;
use App\Services\AgentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Agent extends ControllerBase
{
    /**
     * 新增代理商账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'divide_type',
                'type' => 'required',
                'error_code' => 'divide_type_is_required'
            ],
            [
                'key' => 'agent_type',
                'type' => 'required',
                'error_code' => 'agent_type_is_required'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key' => 'country_id',
                'type' => 'required',
                'error_code' => 'country_id_is_required'
            ],
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentService::addAgent($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 编辑代理商账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'mobile',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'divide_type',
                'type' => 'required',
                'error_code' => 'divide_type_is_required'
            ],
            [
                'key' => 'agent_type',
                'type' => 'required',
                'error_code' => 'agent_type_is_required'
            ],
            [
                'key' => 'country_code',
                'type' => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key' => 'country_id',
                'type' => 'required',
                'error_code' => 'country_id_is_required'
            ],
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            AgentService::updateAgent($params, $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 代理商账户详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AgentService::detailAgent($params['agent_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 代理商账户运营数据概要
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function agentStaticsData(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AgentService::agentStaticsData($params['agent_id']);
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 代理商账户列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['only_read_self'] = self::getEmployeeDataPermission();
        $data = AgentService::listAgent($params, self::getEmployeeId());
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 冻结代理商账户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function freezeAgent(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = self::getEmployeeId();
            AgentService::freezeAgent($params['agent_id'], $employeeId, AgentOperationLogModel::OP_TYPE_FREEZE_AGENT);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 解除冻结
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function unFreezeAgent(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_id',
                'type' => 'required',
                'error_code' => 'agent_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = self::getEmployeeId();
            AgentService::unFreezeAgent($params['agent_id'], $employeeId, AgentOperationLogModel::OP_TYPE_UNFREEZE_AGENT);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 推广学员列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function recommendUsersList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['only_read_self'] = self::getEmployeeDataPermission();
        $data = AgentService::recommendUsersList($params, self::getEmployeeId());
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 推广订单列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function recommendBillsList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['only_read_self'] = self::getEmployeeDataPermission();
        $data = AgentService::recommendBillsList($params, self::getEmployeeId());
        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 代理申请列表接口
     */
    public function applyList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = AgentService::applyList($params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 代理申请列表添加备注接口
     */
    public function applyRemark(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'required',
                'error_code' => 'remark_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'value' => 200,
                'error_code' => 'remark_max_length_is_200'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        AgentService::applyRemark($params);
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 推广素材增加和编辑接口
     */
    public function popularMaterial(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster',
                'type' => 'required',
                'error_code' => 'poster_is_required'
            ],
            [
                'key' => 'text',
                'type' => 'lengthMax',
                'value' => 200,
                'error_code' => 'text_max_length_is_200'
            ],
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        AgentService::popularMaterial($params);
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 推广素材信息获取接口
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function popularMaterialInfo(/** @noinspection PhpUnusedParameterInspection */
        Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'package_id',
                'type' => 'required',
                'error_code' => 'package_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = AgentService::popularMaterialInfo(0, $params['package_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 一级代理模糊搜索
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function agentFuzzySearch(Request $request, Response $response)
    {
        $params = $request->getParams();
        $data = AgentService::agentFuzzySearch($params);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取不同分成模式允许推广的商品包列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function agentDivisionToPackage(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params = $request->getParams();
        $data = AgentService::getAgentDivisionModelToPackage($params['division_model']);
        return HttpHelper::buildResponse($response, $data);
    }


}