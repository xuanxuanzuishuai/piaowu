<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\Constants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\AgentBillMapModel;
use App\Models\MessagePushRulesModel;
use App\Models\PosterModel;
use App\Models\WeChatAwardCashDealModel;
use App\Services\ReferralActivityService;
use App\Libs\Exceptions\RunTimeException;
use App\Services\UserRefereeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Dss extends ControllerBase
{
    /**
     * 获取可生成海报的活动列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function activeList(Request $request, Response $response)
    {
        $params = $request->getParams();
        $result = ReferralActivityService::getActiveList($params);

        return HttpHelper::buildResponse($response, $result);
    }

    /**
     * 获取活动海报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getPoster(Request $request, Response $response)
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
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            $activity = ReferralActivityService::getEmployeePoster($params['activity_id'], $params['employee_id']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, $activity);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 分享海报，返回参数ID
     */
    public static function getParamsId(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'app_id',
                'type' => 'required',
                'error_code' => 'app_id_is_required'
            ],
            [
                'key' => 'type',
                'type' => 'required',
                'error_code' => 'type_is_required'
            ],
            [
                'key' => 'user_id',
                'type' => 'required',
                'error_code' => 'user_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $id = ReferralActivityService::getParamsId($params);
        return HttpHelper::buildResponse($response, ['id' => $id]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * 分享海报根据参数ID返回参数信息
     */
    public static function getParamsInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'param_id',
                'type' => 'required',
                'error_code' => 'param_id_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $paramInfo = ReferralActivityService::getParamsInfo($params['param_id']);
        return HttpHelper::buildResponse($response, json_decode($paramInfo, true));
    }

    /**
     * 创建转介绍关系
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function createRelation(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'uuid',
                'type' => 'required',
                'error_code' => 'uuid_is_required'
            ],
            [
                'key' => 'qr_ticket',
                'type' => 'required',
                'error_code' => 'qr_ticket_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            UserRefereeService::registerDeal($params['student_id'], $params['uuid'], $params['qr_ticket'], $params['app_id'], $params['ext_params']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 红包信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'award_id',
                'type' => 'required',
                'error_code' => 'award_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        $data = WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => explode(',', $params['award_id'])]);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 海报底图数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function posterBaseInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'poster_id',
                'type' => 'required',
                'error_code' => 'poster_id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = PosterModel::getRecord(['id' => $params['poster_id'], 'status' => Constants::STATUS_TRUE], ['path']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 获取消息信息
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function messageInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'id',
                'type' => 'required',
                'error_code' => 'id_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $data = MessagePushRulesModel::getById($params['id']);
        return HttpHelper::buildResponse($response, $data);
    }

    /**
     * 创建代理和订单映射关系
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function makeAgentBillMap(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'student_id',
                'type' => 'required',
                'error_code' => 'student_id_is_required'
            ],
            [
                'key' => 'parent_bill_id',
                'type' => 'required',
                'error_code' => 'parent_bill_id_is_required'
            ],
            [
                'key' => 'qr_ticket',
                'type' => 'required',
                'error_code' => 'qr_ticket_is_required'
            ]
        ];
        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        $res = AgentBillMapModel::add($params['qr_ticket'], $params['parent_bill_id'], $params['student_id']);
        return HttpHelper::buildResponse($response, ['res' => $res]);
    }
}