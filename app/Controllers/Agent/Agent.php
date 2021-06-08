<?php

namespace App\Controllers\Agent;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\KeyErrorRC4Exception;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\AgentOperationLogModel;
use App\Services\AgentOrgService;
use App\Services\AgentService;
use App\Services\CommonServiceForApp;
use App\Services\PackageService;
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
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function getConfig(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::popularMaterialInfo($userInfo['user_id'], $params['package_id'] ?? 0);
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
            [
                'key'        => 'country_id',
                'type'       => 'required',
                'error_code' => 'country_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            Util::containEmoji($params['name'], true);
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
            Util::containEmoji($params['name'], true);
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
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::freezeAgent($params['agent_id'], $userInfo['user_id'], AgentOperationLogModel::OP_TYPE_AGENT_FREEZE_AGENT);
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
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::unFreezeAgent($params['agent_id'], $userInfo['user_id'], AgentOperationLogModel::OP_TYPE_AGENT_UNFREEZE_AGENT);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 国际区号列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function countryCode(Request $request, Response $response)
    {
        $countryCode = CommonServiceForApp::getCountryCode();
        // 热门国际区号 + 全部区号国家名字母序
        $hot = [];
        $list = [];
        array_walk($countryCode, function ($item) use (&$hot, &$list) {
            if ($item['hot'] > 0) {
                $hot['hot'][] = $item;
            }
            $u = strtoupper(substr($item['pinyin'], 0, 1));
            if (!isset($list[$u])) {
                $list[$u] = [];
            }
            $list[$u][] = $item;
        });
        usort($hot['hot'], function ($a, $b) {
            return $a['hot'] > $b['hot'];
        });
        ksort($list);
        return HttpHelper::buildResponse($response, array_merge($hot, $list));
    }

    /**
     * 推广商品列表
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public function packageList(Request $request, Response $response)
    {
        try {
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::getPackageList($userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 产品包详情
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws KeyErrorRC4Exception
     */
    public function packageDetail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = PackageService::getPackageV1Detail($params['package_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 产品包推广信息
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws KeyErrorRC4Exception
     */
    public function packageShareInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'package_id',
                'type'       => 'required',
                'error_code' => 'package_id_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $userInfo = $this->ci['user_info'];
            if (empty($userInfo['user_id'])) {
                throw new RunTimeException(['agent_not_exist']);
            }
            $data = AgentService::getShareInfo($params['package_id'], $userInfo['user_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 线下代理机构封面展示数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgCoverData(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $data = AgentOrgService::orgMiniCoverData($this->ci['user_info']['user_id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 线下代理机构关联曲谱教材合集列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgOpnList(/** @noinspection PhpUnusedParameterInspection */ Request $request, Response $response)
    {
        $data = AgentOrgService::orgMiniOpnList($this->ci['user_info']['user_id']);
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 线下代理机构关联曲谱教材合集列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgStudentList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['agent_id'] = $this->ci['user_info']['user_id'];
            list($params['page'], $params['count']) = Util::formatPageCount($params);
            $data = AgentOrgService::studentList($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}