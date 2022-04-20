<?php
/**
 * 周周有奖 - 活动管理
 */

namespace App\Controllers\OrgWeb;

use App\Controllers\ControllerBase;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Erp\ErpEventModel;
use App\Models\OperationActivityModel;
use App\Services\SharePosterDesignateUuidService;
use App\Services\UserService;
use App\Services\WeekActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class WeekActivity extends ControllerBase
{
    /**
     * 添加活动
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
                'key' => 'event_id',
                'type' => 'required',
                'error_code' => 'event_id_is_required'
            ],
            [
                'key' => 'guide_word',
                'type' => 'required',
                'error_code' => 'guide_word_is_required'
            ],
            [
                'key' => 'guide_word',
                'type' => 'lengthMax',
                'value' => 1000,
                'error_code' => 'guide_word_length_invalid'
            ],
            [
                'key' => 'share_word',
                'type' => 'required',
                'error_code' => 'share_word_is_required'
            ],
            [
                'key' => 'share_word',
                'type' => 'lengthMax',
                'value' => 1000,
                'error_code' => 'share_word_length_invalid'
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
                'key' => 'banner',
                'type' => 'required',
                'error_code' => 'banner_is_required'
            ],
            [
                'key' => 'share_button_img',
                'type' => 'required',
                'error_code' => 'share_button_img_is_required'
            ],
            [
                'key' => 'award_detail_img',
                'type' => 'required',
                'error_code' => 'award_detail_img_is_required'
            ],
            [
                'key' => 'upload_button_img',
                'type' => 'required',
                'error_code' => 'upload_button_img_is_required'
            ],
            [
                'key' => 'strategy_img',
                'type' => 'required',
                'error_code' => 'strategy_img_is_required'
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
            ],
            [
                'key' => 'personality_poster_button_img',
                'type' => 'required',
                'error_code' => 'personality_poster_button_img_is_required'
            ],
            [
                'key' => 'poster_prompt',
                'type' => 'required',
                'error_code' => 'poster_prompt_is_required'
            ],
            [
                'key' => 'poster_make_button_img',
                'type' => 'required',
                'error_code' => 'poster_make_button_img_is_required'
            ],
            [
                'key' => 'share_poster_prompt',
                'type' => 'required',
                'error_code' => 'share_poster_prompt_is_required'
            ],
            [
                'key' => 'retention_copy',
                'type' => 'required',
                'error_code' => 'retention_copy_is_required'
            ],
            [
                'key' => 'poster_order',
                'type' => 'required',
                'error_code' => 'poster_order_is_required'
            ],
            [
                'key' => 'poster_order',
                'type' => 'integer',
                'error_code' => 'poster_order_is_integer'
            ],
            [
                'key' => 'delay_day',
                'type' => 'integer',
                'error_code' => 'delay_day_is_integer'
            ],
            [
                'key' => 'priority_level',
                'type' => 'integer',
                'error_code' => 'priority_level_is_integer'
            ],
            [
                'key' => 'award_prize_type',
                'type' => 'required',
                'error_code' => 'award_prize_type_is_required'
            ],
            [
                'key' => 'award_prize_type',
                'type' => 'in',
                'value'=>[OperationActivityModel::AWARD_PRIZE_TYPE_IN_TIME, OperationActivityModel::AWARD_PRIZE_TYPE_DELAY],
                'error_code' => 'award_prize_type_value_error'
            ],
            [
                'key' => 'delay_day',
                'type' => 'max',
                'value' => 10,
                'error_code' => 'delay_day_max_is_ten'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $employeeId = $this->getEmployeeId();
            if (empty($params['poster']) && empty($params['personality_poster'])) {
                throw new RunTimeException(['poster_or_personality_poster_is_required']);
            }
            if (!empty($params['activity_id'])) {
                $data = WeekActivityService::edit($params, $employeeId);
            } else {
                $data = WeekActivityService::add($params, $employeeId);
            }
        } catch (RuntimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取周周领奖活动列表
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
            $data = WeekActivityService::searchList($params, $page, $limit);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取event事件列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function eventTaskList(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $data = [];
            $eventId = !empty($params['event_id']) ? intval($params['event_id']) : 0;
            $eventType = !empty($params['event_type']) ? $params['event_type'] : ErpEventModel::DAILY_UPLOAD_POSTER;
            $eventResult = (new Erp())->eventTaskList($eventId, $eventType);
            if (!empty($eventResult['data']) && is_array($eventResult['data'])) {
                $data = $eventResult['data'];
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取周周领奖详情
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
            $data = WeekActivityService::getDetailById($params['activity_id']);
            if (isset($data['ab_test']['allocation_mode'])) {
                unset($data['ab_test']['allocation_mode']);
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 周周领奖的活动 启用和禁用
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
            WeekActivityService::editEnableStatus($params['activity_id'], $params['enable_status'], $employeeId);
        } catch (RunTimeException $e) {
            return HttpHelper::buildOrgWebErrorResponse($response, $e->getWebErrorData(), $e->getData());
        }
        return HttpHelper::buildResponse($response, []);
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
            $result = WeekActivityService::sendActivitySMS($params['activity_id'], $employeeId);
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
            $result = WeekActivityService ::sendWeixinMessage($params['activity_id'], $employeeId, $params['guide_word'], $params['share_word'], $params['poster_url']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 智能 - 删除活动指定用户UUID
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function designateUUIDDel(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'designate_uuid',
                'type' => 'required',
                'error_code' => 'designate_uuid_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = self::getEmployeeId();
        try {
            SharePosterDesignateUuidService::delActivityDesignateUUID($params['activity_id'], $employeeId, $params['designate_uuid']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 智能 - 获取指定活动的UUID列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function designateUUIDList(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
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
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $limit) = Util::formatPageCount($params);
        $data = SharePosterDesignateUuidService::getActivityDesignateUUIDList($params['activity_id'], $page, $limit);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 智能 - 检查活动指定用户UUID
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function designateUUIDCheck(Request $request, Response $response): Response
    {
        $rules = [
            [
                'key' => 'designate_uuid',
                'type' => 'required',
                'error_code' => 'designate_uuid_is_required'
            ],
            [
                'key' => 'activity_id',
                'type' => 'integer',
                'error_code' => 'activity_id_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $designateUUID = is_array($params['designate_uuid']) ? $params['designate_uuid'] : [];
            $data = UserService::checkDssStudentUuidExists($designateUUID, $params['activity_id'] ?? 0);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $data);
    }
}
