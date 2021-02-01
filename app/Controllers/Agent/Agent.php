<?php

namespace App\Controllers\Agent;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\GoodsResourceModel;
use App\Services\AgentService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Agent extends ControllerBase
{

    /**
     * 代理小程序首页
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function miniAppIndex(Request $request, Response $response)
    {
        try {
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::getMiniAppIndex($userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 推广素材
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getConfig(Request $request, Response $response)
    {
        try {
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::popularMaterialInfo($userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 二级代理列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentList(Request $request, Response $response)
    {
        try {
            $params   = $request->getParams();
            $userInfo = $this->ci['user_info'];
            $data     = AgentService::secAgentList($userInfo['user_id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 我的上级代理
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentParent(Request $request, Response $response)
    {
        try {
            $userInfo = $this->ci['user_info'];
            $data     = AgentService::secAgentParent($userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 二级代理详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentDetail(Request $request, Response $response)
    {
        try {
            $rules = [
                [
                    'key'        => 'agent_id',
                    'type'       => 'required',
                    'error_code' => 'agent_id_is_required'
                ],
            ];
            $params = $request->getParams();
            $result = Valid::appValidate($params, $rules);
            if ($result['code'] != Valid::CODE_SUCCESS) {
                return $response->withJson($result, StatusCode::HTTP_OK);
            }
            $data = AgentService::secAgentDetail($params['agent_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 添加二级代理
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentAdd(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $userInfo = $this->ci['user_info'];
            AgentService::secAgentAdd($userInfo['user_id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 更新二级代理
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentUpdate(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required',
            ],
            [
                'key'        => 'mobile',
                'type'       => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = AgentService::secAgentUpdate($params['agent_id'], $params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 冻结代理商
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentFreeze(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = AgentService::freezeAgent($params['agent_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 解冻代理商
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function secAgentUnfreeze(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'agent_id',
                'type'       => 'required',
                'error_code' => 'agent_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = AgentService::unFreezeAgent($params['agent_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}