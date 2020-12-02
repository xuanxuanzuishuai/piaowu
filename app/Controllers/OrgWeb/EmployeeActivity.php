<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class EmployeeActivity extends ControllerBase
{
    /**
     * 员工专项转介绍活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            list($activities, $totalCount) = ReferralActivityService::getEmployeeActivities($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'activities' => $activities,
            'total_count' => $totalCount
        ]);
    }

    /**
     * 添加转介绍活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function add(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'length_invalid'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'rules',
                'type'       => 'required',
                'error_code' => 'rules_is_required'
            ],
            [
                'key'        => 'banner',
                'type'       => 'required',
                'error_code' => 'banner_is_required'
            ],
            [
                'key'        => 'invite_text',
                'type'       => 'required',
                'error_code' => 'invite_text_is_required'
            ],
            [
                'key'        => 'poster',
                'type'       => 'required',
                'error_code' => 'poster_is_required'
            ],
            [
                'key'        => 'employee_share',
                'type'       => 'required',
                'error_code' => 'employee_share_is_required'
            ],
            [
                'key'        => 'employee_poster',
                'type'       => 'required',
                'error_code' => 'employee_poster_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $activity = ReferralActivityService::addEmployeeActivity($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * 修改转介绍活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function modify(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key'        => 'name',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'length_invalid'
            ],
            [
                'key'        => 'start_time',
                'type'       => 'required',
                'error_code' => 'start_time_is_required'
            ],
            [
                'key'        => 'end_time',
                'type'       => 'required',
                'error_code' => 'end_time_is_required'
            ],
            [
                'key'        => 'rules',
                'type'       => 'required',
                'error_code' => 'rules_is_required'
            ],
            [
                'key'        => 'banner',
                'type'       => 'required',
                'error_code' => 'banner_is_required'
            ],
            [
                'key'        => 'invite_text',
                'type'       => 'required',
                'error_code' => 'invite_text_is_required'
            ],
            [
                'key'        => 'poster',
                'type'       => 'required',
                'error_code' => 'poster_is_required'
            ],
            [
                'key'        => 'employee_share',
                'type'       => 'required',
                'error_code' => 'employee_share_is_required'
            ],
            [
                'key'        => 'employee_poster',
                'type'       => 'required',
                'error_code' => 'employee_poster_is_required'
            ],
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activityId = $params['activity_id'];
            unset($params['activity_id']);
            $activity = ReferralActivityService::modifyEmployeeActivity($params, $activityId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * 活动详情
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function detail(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getEmployeeActivityDetail($params['activity_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * 启用、禁用活动
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateStatus(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'status',
                'type'       => 'required',
                'error_code' => 'status_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $result = ReferralActivityService::updateEmployeeActivityStatus($params['activity_id'], $params['status']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }
}