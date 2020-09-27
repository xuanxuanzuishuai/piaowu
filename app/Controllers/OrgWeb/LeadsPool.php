<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Libs\Valid;
use App\Libs\HttpHelper;
use App\Services\LeadsPoolService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

/**
 * 粒子分配池控制器
 * Class Collection
 * @package App\Controllers\OrgWeb
 */
class LeadsPool extends ControllerBase
{

    /**
     * 添加线索分配池
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'pool_name',
                'type' => 'required',
                'error_code' => 'pool_name_is_required'
            ],
            [
                'key' => 'target_type',
                'type' => 'required',
                'error_code' => 'target_type_is_required'
            ],
            [
                'key' => 'alloc_rules',
                'type' => 'required',
                'error_code' => 'alloc_rules_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            LeadsPoolService::add($params, $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 修改线索分配池
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'pool_id',
                'type' => 'required',
                'error_code' => 'pool_id_is_required'
            ],
            [
                'key' => 'pool_name',
                'type' => 'required',
                'error_code' => 'pool_name_is_required'
            ],
            [
                'key' => 'target_type',
                'type' => 'required',
                'error_code' => 'target_type_is_required'
            ],
            [
                'key' => 'alloc_rules',
                'type' => 'required',
                'error_code' => 'alloc_rules_is_required'
            ]
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            LeadsPoolService::update($params, $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 修改线索分配池状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updatePoolStatus(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'pool_id',
                'type' => 'required',
                'error_code' => 'pool_id_is_required'
            ],
            [
                'key' => 'pool_status',
                'type' => 'required',
                'error_code' => 'pool_status_is_required'
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            LeadsPoolService::updatePoolStatus($params['pool_id'], $params['pool_status'], $this->getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }


    /**
     * 获取线索分配池数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        //接收数据
        $rules = [
            [
                'key' => 'pool_id',
                'type' => 'required',
                'error_code' => 'pool_id_is_required'
            ],
        ];
        //验证合法性
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = LeadsPoolService::detail($params['pool_id']);
        return HttpHelper::buildResponse($response, $data[0]);
    }

    /**
     * 获取线索分配池列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPoolList(Request $request, Response $response)
    {
        $params = $request->getParams();
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $params['count'] = 100;
        $data = LeadsPoolService::getPoolList($params['page'], $params['count']);
        return HttpHelper::buildResponse($response, $data);
    }
}