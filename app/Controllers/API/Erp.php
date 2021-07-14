<?php
/**
 * 接收erp服务相关请求
 */

namespace App\Controllers\API;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\OperationActivityModel;
use App\Models\RtActivityModel;
use App\Services\ErpUserEventTaskAwardGoldLeafService;
use App\Services\RtActivityService;
use App\Services\UserPointsExchangeOrderService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Erp extends ControllerBase
{
    /**
     * 积分兑换红包
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function integralExchangeRedPack(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'order_id',
                'type' => 'required',
                'error_code' => 'order_id_is_required',
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'points_exchange',
                'type' => 'required',
                'error_code' => 'points_exchange_is_required',
            ],
            [
                'key' => 'red_amounts',
                'type' => 'required',
                'error_code' => 'red_amounts_is_required',
            ],
            [
                'key' => 'sign',
                'type' => 'required',
                'error_code' => 'sign_is_required',
            ],
            [
                'key' => 'award_id',
                'type' => 'required',
                'error_code' => 'award_id_is_required',
            ],
            [
                'key' => 'points_exchange',
                'type' => 'integer',
                'error_code' => 'points_exchange_is_integer',
            ],
            [
                'key' => 'red_amounts',
                'type' => 'integer',
                'error_code' => 'red_amounts_is_integer',
            ],
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['record_sn'] = $params['award_id'];
            $res = UserPointsExchangeOrderService::toRedPack($params);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * 获取待发放金叶子积分明细
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function goldLeafList(Request $request, Response $response) {
        $rules = [
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required',
            ],
            [
                'key' => 'page',
                'type' => 'integer',
                'error_code' => 'page_is_integer'
            ],
            [
                'key' => 'count',
                'type' => 'integer',
                'error_code' => 'count_is_integer'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            list($page, $limit) = Util::formatPageCount($params);
            $res = ErpUserEventTaskAwardGoldLeafService::getWaitingGoldLeafList($params, $page, $limit, true);
        } catch (RunTimeException $e) {
            SimpleLogger::info("Erp::integralExchangeRedPack error", ['params' => $params, 'err' => $e->getData()]);
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $res);
    }

    /**
     * rt亲友优惠券活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function rtActivityList(Request $request, Response $response)
    {
        $params = $request->getParams();
        try {
            $ruleType = OperationActivityModel::RULE_TYPE_ASSISTANT;
            $page = 1;
            $count = 1000;
            $activityName = $params['name'] ?? '';
            $activityList = RtActivityService::getRtActivityList($ruleType, $activityName, $page, $count);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'list' => $activityList,
            'student_status' => [
                DssStudentModel::REVIEW_COURSE_49 => DssStudentModel::CURRENT_PROGRESS[DssStudentModel::REVIEW_COURSE_49],
                DssStudentModel::REVIEW_COURSE_1980 => DssStudentModel::CURRENT_PROGRESS[DssStudentModel::REVIEW_COURSE_1980],
            ]
        ]);
    }

    /**
     * 获取海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getRtPoster(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'activity_id',
                'type' => 'required',
                'error_code' => 'activity_id_is_required'
            ],
            [
                'key' => 'employee_id',
                'type' => 'required',
                'error_code' => 'employee_id_is_required'
            ],
            [
                'key' => 'employee_uuid',
                'type' => 'required',
                'error_code' => 'employee_uuid_is_required'
            ],
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $params['type'] = RtActivityModel::ACTIVITY_RULE_TYPE_KEGUAN;
            $activity = RtActivityService::getPoster($params);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }
        return HttpHelper::buildResponse($response, $activity);
    }
}
