<?php
/**
 * 限时活动 - 活动管理
 */

namespace App\Controllers\OrgWeb\Activity;

use App\Controllers\ControllerBase;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Erp\ErpEventModel;
use App\Models\OperationActivityModel;
use App\Services\Activity\LimitTimeActivity\LimitTimeActivityAdminService;
use App\Services\SharePosterDesignateUuidService;
use App\Services\UserService;
use App\Services\WeekActivityService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class LimitTimeActivity extends ControllerBase
{
    /**
     * 添加限时活动
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws RunTimeException
     */
    public function save(Request $request, Response $response)
    {
        $rules = [
            /** 限时活动基本信息参数 */
            [
                'key'        => 'app_id',
                'type'       => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
                'error_code' => 'activity_id_is_integer'
            ],
            [
                'key'        => 'activity_name',
                'type'       => 'required',
                'error_code' => 'activity_name_is_required'
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
                'key'        => 'country_code',
                'type'       => 'required',
                'error_code' => 'country_code_is_required'
            ],
            [
                'key'        => 'target_user_type',
                'type'       => 'required',
                'error_code' => 'target_user_type_is_required'
            ],
            [
                'key'        => 'target_user',
                'type'       => 'required',
                'error_code' => 'target_user_is_required'
            ],
            [
                'key'        => 'activity_type',
                'type'       => 'required',
                'error_code' => 'activity_type_is_required'
            ],
            [
                'key'        => 'award_type',
                'type'       => 'required',
                'error_code' => 'award_type_is_required'
            ],
            [
                'key'        => 'award_prize_type',
                'type'       => 'required',
                'error_code' => 'award_prize_type_is_required'
            ],
            [
                'key'        => 'award_prize_type',
                'type'       => 'in',
                'value'      => [OperationActivityModel::AWARD_PRIZE_TYPE_IN_TIME, OperationActivityModel::AWARD_PRIZE_TYPE_DELAY],
                'error_code' => 'award_prize_type_value_error'
            ],
            [
                'key'        => 'delay_day',
                'type'       => 'integer',
                'error_code' => 'delay_day_is_integer'
            ],
            [
                'key'        => 'delay_day',
                'type'       => 'max',
                'value'      => 10,
                'error_code' => 'delay_day_max_is_ten'
            ],
            /** 页面配置参数 */
            [
                'key'        => 'guide_word',
                'type'       => 'required',
                'error_code' => 'guide_word_is_required'
            ],
            [
                'key'        => 'guide_word',
                'type'       => 'lengthMax',
                'value'      => 1000,
                'error_code' => 'guide_word_length_invalid'
            ],
            [
                'key'        => 'share_word',
                'type'       => 'required',
                'error_code' => 'share_word_is_required'
            ],
            [
                'key'        => 'share_word',
                'type'       => 'lengthMax',
                'value'      => 1000,
                'error_code' => 'share_word_length_invalid'
            ],
            [
                'key'        => 'banner',
                'type'       => 'required',
                'error_code' => 'banner_is_required'
            ],
            [
                'key'        => 'share_button_img',
                'type'       => 'required',
                'error_code' => 'share_button_img_is_required'
            ],
            [
                'key'        => 'award_detail_img',
                'type'       => 'required',
                'error_code' => 'award_detail_img_is_required'
            ],
            [
                'key'        => 'upload_button_img',
                'type'       => 'required',
                'error_code' => 'upload_button_img_is_required'
            ],
            [
                'key'        => 'strategy_img',
                'type'       => 'required',
                'error_code' => 'strategy_img_is_required'
            ],
            [
                'key'        => 'remark',
                'type'       => 'lengthMax',
                'value'      => 50,
                'error_code' => 'remark_length_invalid'
            ],
            [
                'key'        => 'personality_poster_button_img',
                'type'       => 'required',
                'error_code' => 'personality_poster_button_img_is_required'
            ],
            [
                'key'        => 'poster_prompt',
                'type'       => 'required',
                'error_code' => 'poster_prompt_is_required'
            ],
            [
                'key'        => 'poster_make_button_img',
                'type'       => 'required',
                'error_code' => 'poster_make_button_img_is_required'
            ],
            [
                'key'        => 'share_poster_prompt',
                'type'       => 'required',
                'error_code' => 'share_poster_prompt_is_required'
            ],
            [
                'key'        => 'retention_copy',
                'type'       => 'required',
                'error_code' => 'retention_copy_is_required'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $employeeId = $this->getEmployeeId();
        if (!empty($params['activity_id'])) {
            $data = WeekActivityService::edit($params, $employeeId);
        } else {
            $data = LimitTimeActivityAdminService::add($params, $employeeId);
        }
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取限时领奖活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        $rules = [
            [
                'key'        => 'count',
                'type'       => 'integer',
                'error_code' => 'count_is_integer'
            ],
            [
                'key'        => 'page',
                'type'       => 'integer',
                'error_code' => 'page_is_integer'
            ],
        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        list($page, $limit) = Util::formatPageCount($params);
        $data = LimitTimeActivityAdminService::searchList($params, $page, $limit);
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
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
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
                'key'        => 'activity_id',
                'type'       => 'required',
                'error_code' => 'id_is_required'
            ],
            [
                'key'        => 'activity_id',
                'type'       => 'integer',
                'error_code' => 'id_is_integer'
            ],
            [
                'key'        => 'enable_status',
                'type'       => 'required',
                'error_code' => 'enable_status_is_required'
            ],
            [
                'key'        => 'enable_status',
                'type'       => 'integer',
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

}
