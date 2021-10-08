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
use App\Libs\Valid;
use App\Models\ReferralRulesModel;
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
                'key' => 'start_time',
                'type' => 'integer',
                'error_code' => 'start_time_is_positive_integer'
            ],
            [
                'key' => 'end_time',
                'type' => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key' => 'end_time',
                'type' => 'integer',
                'error_code' => 'end_time_is_positive_integer'
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
        //规则类型
        $params['rule_type'] = ReferralRulesModel::TYPE_AI_STUDENT_REFEREE;
        try {
            $data = ReferralRuleService::add($params, self::getEmployeeId());
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
