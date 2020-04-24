<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/20
 * Time: 6:03 PM
 */

namespace App\Controllers\OrgWeb;


use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Services\ErpReferralService;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\Erp;
use Slim\Http\StatusCode;

class Activity extends ControllerBase
{
    /**
     * 活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $params = $request->getParams();

        try {
            list($activities, $totalCount) = ReferralActivityService::activities($params);
        } catch (RunTimeException $e) {
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
                'key' => 'event_id',
                'type' => 'required',
                'error_code' => 'event_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 50,
                'error_code' => 'name_max_length_is_64'
            ],
            [
                'key' => 'guide_word',
                'type' => 'required',
                'error_code' => 'guide_word_is_required'
            ],
            [
                'key' => 'share_word',
                'type' => 'required',
                'error_code' => 'share_word_is_required'
            ],
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
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
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['remark'] = $params['remark'] ?? '';
        $params['operator_id'] = self::getEmployeeId();

        try {
            $activity = ReferralActivityService::addActivity($params);
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
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'name',
                'type' => 'lengthMax',
                'value' => 50,
                'error_code' => 'name_max_length_is_64'
            ],
            [
                'key' => 'name',
                'type' => 'required',
                'error_code' => 'name_is_required'
            ],
            [
                'key' => 'guide_word',
                'type' => 'required',
                'error_code' => 'guide_word_is_required'
            ],
            [
                'key' => 'share_word',
                'type' => 'required',
                'error_code' => 'share_word_is_required'
            ],
            [
                'key' => 'poster_url',
                'type' => 'required',
                'error_code' => 'poster_url_is_required'
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
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $params['remark'] = $params['remark'] ?? '';

        try {
            $activity = ReferralActivityService::modify($params);
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
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getActivityDetail($params['activity_id']);
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
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'status',
                'type' => 'required',
                'error_code' => 'status_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }


        try {
            $result = ReferralActivityService::updateStatus($params['activity_id'], $params['status']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 获取事件任务
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function eventTasks(Request $request, Response $response)
    {

        $erp = new Erp();
        $events = $erp->eventTaskList();

        $data = [];
        if (!empty($events) && $events['code'] == Valid::CODE_SUCCESS) {
            $data = $events['data'];
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'data' => $data
        ], StatusCode::HTTP_OK);
    }

    /**
     * 发送短信提醒
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function sendMsg(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();

        try {
            $result = ReferralActivityService::sendActivitySMS($params['activity_id'], $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 发送微信消息提醒
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pushWeixinMsg(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $employeeId = self::getEmployeeId();

        $params['guide_word'] = $params['guide_word'] ?? '';
        $params['share_word'] = $params['share_word'] ?? '';
        $params['poster_url'] = $params['poster_url'] ?? '';

        try {
            $result = ReferralActivityService::sendWeixinMessage($params['activity_id'], $employeeId,
                $params['guide_word'], $params['share_word'], $params['poster_url']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);

    }

}