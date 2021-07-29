<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/8/2
 * Time: 14:09
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AgentModel;
use App\Models\AgentOperationLogModel;
use App\Services\AgentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class BusinessAgent extends ControllerBase
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
                'key' => 'province_code',
                'type' => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key' => 'city_code',
                'type' => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
            [
                'key' => 'organization',
                'type' => 'required',
                'error_code' => 'organization_is_required'
            ],
            [
                'key' => 'organization',
                'type' => 'lengthMax',
                'value' => 20,
                'error_code' => 'organization_max_length_is_20'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'required',
                'error_code' => 'leads_allot_type_is_required'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'leads_allot_type_is_error'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $params['agent_type'] = AgentModel::TYPE_OFFLINE;
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
                'key' => 'organization',
                'type' => 'required',
                'error_code' => 'organization_is_required'
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
                'key' => 'province_code',
                'type' => 'required',
                'error_code' => 'province_code_is_required'
            ],
            [
                'key' => 'city_code',
                'type' => 'required',
                'error_code' => 'city_code_is_required'
            ],
            [
                'key' => 'division_model',
                'type' => 'required',
                'error_code' => 'division_model_is_required'
            ],
            [
                'key' => 'organization',
                'type' => 'lengthMax',
                'value' => 20,
                'error_code' => 'organization_max_length_is_20'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'required',
                'error_code' => 'leads_allot_type_is_required'
            ],
            [
                'key' => 'leads_allot_type',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'leads_allot_type_is_error'
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
        try {
            $data = AgentService::listAgent($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
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
}