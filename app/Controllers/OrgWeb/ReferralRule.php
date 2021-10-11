<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/10/08
 * Time: 11:41
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\ReferralRuleService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class ReferralRule extends ControllerBase
{
    /**
     * 转介绍奖励规则新增
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
                'key' => 'name',
                'type' => 'lengthMax',
                'error_code' => 'real_name_length_error',
                'value' => 50
            ],
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key' => 'trail_rule',
                'type' => 'required',
                'error_code' => 'trail_rule_is_required'
            ],
            [
                'key' => 'normal_rule',
                'type' => 'required',
                'error_code' => 'normal_rule_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'error_code' => 'rule_remark_length_max_100',
                'value' => 100
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ReferralRuleService::add($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


    /**
     * 转介绍奖励规则编辑
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function update(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rule_id',
                'type' => 'required',
                'error_code' => 'rule_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'error_code' => 'real_name_length_error',
                'value' => 50
            ],
            [
                'key' => 'start_time',
                'type' => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key' => 'trail_rule',
                'type' => 'required',
                'error_code' => 'trail_rule_is_required'
            ],
            [
                'key' => 'normal_rule',
                'type' => 'required',
                'error_code' => 'normal_rule_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'error_code' => 'rule_remark_length_max_100',
                'value' => 100
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ReferralRuleService::update($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 转介绍奖励规则详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rule_id',
                'type' => 'required',
                'error_code' => 'rule_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ReferralRuleService::detail($params['rule_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, ['data' => $data, 'operation_button' => self::getEmployeeOperationButton()]);
    }

    /**
     * 转介绍奖励规则列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rule_id',
                'type' => 'integer',
                'error_code' => 'rule_id_must_be_integer'
            ],
            [
                'key' => 'rule_type',
                'type' => 'integer',
                'error_code' => 'rule_type_must_be_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'status_is_must_be_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'status_is_invalid'
            ],
            [
                'key' => 'time_status',
                'type' => 'integer',
                'error_code' => 'status_is_must_be_integer'
            ],
            [
                'key' => 'time_status',
                'type' => 'in',
                'value' => [1, 2, 3],
                'error_code' => 'status_is_invalid'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($params['page'], $params['count']) = Util::formatPageCount($params);
        $data = ReferralRuleService::list($params);
        return HttpHelper::buildResponse($response, ['data' => $data, 'operation_button' => self::getEmployeeOperationButton()]);
    }


    /**
     * 转介绍奖励规则列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function enable(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rule_id',
                'type' => 'integer',
                'error_code' => 'rule_id_must_be_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'status_is_must_be_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'in',
                'value' => [2, 3],
                'error_code' => 'status_is_invalid'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ReferralRuleService::updateEnableStatus($params['rule_id'], $params['enable_status'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 转介绍奖励规则复制
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function copy(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'rule_id',
                'type' => 'integer',
                'error_code' => 'rule_id_must_be_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = ReferralRuleService::copy($params['rule_id'], self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }


}
