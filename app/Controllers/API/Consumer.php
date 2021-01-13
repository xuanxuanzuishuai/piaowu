<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/27
 * Time: 2:05 PM
 */

namespace App\Controllers\API;

use App\Controllers\ControllerBase;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\EmployeeModel;
use App\Services\CashGrantService;
use App\Services\MessageService;
use App\Services\Queue\PushMessageTopic;
use App\Services\UserRefereeService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Consumer extends ControllerBase
{
    /**
     * 更新不同系统的access_token
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function updateAccessToken(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        WeChatMiniPro::factory($params['msg_body']['app_id'], $params['msg_body']['busi_type'])->setAccessToken($params['msg_body']['access_token']);
    }

    /**
     * 转介绍相关
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function refereeAward(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }
        try {
            UserRefereeService::refereeAwardDeal($params['msg_body']['app_id'], $params['event_type'], $params['msg_body']);
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 红包相关
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function redPackDeal(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            CashGrantService::redPackQueueDeal($params['msg_body']['award_id'], $params['event_type'], $params['msg_body']['reviewer_id'] ?: EmployeeModel::SYSTEM_EMPLOYEE_ID, $params['msg_body']['reason'] ?: '');
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 消息推送
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pushMessage(Request $request, Response $response)
    {
        $params = $request->getParams();
        $rules = [
            [
                'key' => 'topic_name',
                'type' => 'required',
                'error_code' => 'topic_name_is_required',
            ],
            [
                'key' => 'source_app_id',
                'type' => 'required',
                'error_code' => 'source_app_id_is_required',
            ],
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],
            [
                'key' => 'msg_body',
                'type' => 'required',
                'error_code' => 'msg_body_is_required',
            ],
        ];

        $result = Valid::validate($params, $rules);
        if ($result['code'] == Valid::CODE_PARAMS_ERROR) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            switch ($params['event_type']) {
                case PushMessageTopic::EVENT_WECHAT_INTERACTION:
                    MessageService::interActionDealMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_USER_BIND_WECHAT:
                    MessageService::boundWxActionDealMessage(
                        $params['msg_body']['open_id'],
                        $params['msg_body']['app_id'],
                        $params['msg_body']['user_type'],
                        $params['msg_body']['busi_type']
                    );
                    break;

                case PushMessageTopic::EVENT_PAY_NORMAL:
                    MessageService::yearPayActionDealMessage($params['msg_body']['user_id'], $params['msg_body']['package_type']);
                    break;

                case PushMessageTopic::EVENT_SUBSCRIBE:
                    $data = MessageService::preSendVerify($params['msg_body']['open_id'], DictConstants::get(DictConstants::MESSAGE_RULE, 'subscribe_rule_id'));
                    if (!empty($data)) {
                        MessageService::realSendMessage($data);
                    }
                    break;

                case PushMessageTopic::EVENT_PUSH_MANUAL_RULE_WX:
                    MessageService::realSendManualMessage($params['msg_body']);
                    break;
                
                case PushMessageTopic::EVENT_PUSH_RULE_WX:
                    MessageService::realSendMessage($params['msg_body']);
                    break;

                case PushMessageTopic::EVENT_UNSUBSCRIBE:
                    MessageService::clearMessageRuleLimit($params['msg_body']['open_id']);
                    break;
            }
        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }
        return HttpHelper::buildResponse($response, []);
    }
}