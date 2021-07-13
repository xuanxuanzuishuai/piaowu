<?php
/**
 * rt活动
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Services\RtActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class RtActivity extends ControllerBase
{
    /**
     * RT亲友优惠券活动添加
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function save(Request $request, Response $response)
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
                'value' => 50,
                'error_code' => 'name_length_invalid'
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
                'key' => 'rule_type',
                'type' => 'required',
                'error_code' => 'rule_type_required'
            ],
            [
                'key' => 'buy_day',
                'type' => 'required',
                'error_code' => 'buy_day_is_required'
            ],
            [
                'key' => 'coupon_num',
                'type' => 'required',
                'error_code' => 'coupon_num_is_required'
            ],
            [
                'key' => 'coupon_id',
                'type' => 'required',
                'error_code' => 'coupon_id_is_required'
            ],
            [
                'key' => 'join_user_status',
                'type' => 'required',
                'error_code' => 'join_user_status_is_required'
            ],
            [
                'key' => 'employee_invite_word',
                'type' => 'required',
                'error_code' => 'employee_invite_word_is_required'
            ],
            [
                'key' => 'student_invite_word',
                'type' => 'required',
                'error_code' => 'student_invite_word_is_invalid'
            ],
            [
                'key' => 'employee_poster',
                'type' => 'required',
                'error_code' => 'employee_poster_is_invalid'
            ],
            [
                'key' => 'poster',
                'type' => 'required',
                'error_code' => 'poster_is_invalid'
            ],
            [
                'key' => 'award_rule',
                'type' => 'required',
                'error_code' => 'award_rule_is_required'
            ],
            [
                'key' => 'remark',
                'type' => 'lengthMax',
                'value' => 50,
                'error_code' => 'remark_length_invalid'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'activity_id_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            if (!empty($params['activity_id'])) {
                RtActivityService::edit($params, $employeeId);
            } else {
                RtActivityService::add($params, $employeeId);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * RT亲友优惠券活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $data = RtActivityService::searchList($params, $page, $limit);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * RT亲友优惠券活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'activity_id_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $data = RtActivityService::getDetailById($params['activity_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 修改RT亲友优惠券活动启用状态
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function editEnableStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'id_is_integer'
            ],
            [
                'key' => 'enable_status',
                'type' => 'required',
                'error_code' => 'enable_status_is_required'
            ],
            [
                'key' => 'enable_status',
                'type' => 'integer',
                'error_code' => 'enable_status_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            RtActivityService::editEnableStatus($params['activity_id'], $params['enable_status'], $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}