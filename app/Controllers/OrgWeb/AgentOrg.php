<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/5/26
 * Time: 10:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\AgentOrgService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class AgentOrg extends ControllerBase
{
    /**
     * 代理商机构专属曲谱教材关联
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgOpnRelation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'agent_org_id',
                'type' => 'required',
                'error_code' => 'agent_org_id_required'
            ],
            [
                'key' => 'opn_id',
                'type' => 'required',
                'error_code' => 'opn_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            AgentOrgService::orgOpnRelation($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 代理商机构专属曲谱教材列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgOpnList(Request $request, Response $response)
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
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $logData = AgentOrgService::orgOpnList($params);
        return HttpHelper::buildResponse($response, $logData);
    }


    /**
     * 代理商机构统计数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function orgStaticsData(Request $request, Response $response)
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
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $logData = AgentOrgService::orgStaticsData($params);
        return HttpHelper::buildResponse($response, $logData);
    }
}