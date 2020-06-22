<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/27
 * Time: 2:05 PM
 */

namespace App\Controllers\API;

use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Controllers\ControllerBase;
use App\Services\ChannelService;
use App\Services\PlayClassRecordMessageService;
use App\Services\Queue\PushMessageTopic;
use App\Services\Queue\TableSyncTopic;
use App\Services\ReferralActivityService;
use App\Libs\TableSyncQueue;
use App\Services\VoiceCall\VoiceCallTRService;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Consumer extends ControllerBase
{
    /**
     * 用户演奏数据上报
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function userPlay(Request $request, Response $response)
    {
        // 参数校验
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

        $ret = PlayClassRecordMessageService::handleMessage($params);

        return HttpHelper::buildResponse($response, ['ret' => $ret]);
    }


    /**
     * 渠道数据
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function channelStatus(Request $request, Response $response)
    {
        // 参数校验
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

        switch ($params['event_type']) {
            case 'channel_sync':
                ChannelService::sync($params['msg_body']);
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 推送微信消息给用户
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function pushMessage(Request $request, Response $response)
    {
        // 参数校验
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

        switch ($params['event_type']) {
            case PushMessageTopic::EVENT_PUSH_WX:
                ReferralActivityService::pushWXMsg($params['msg_body']);
                break;
            case PushMessageTopic::EVENT_PUSH_WX_CASH_SHARE_MESSAGE:
                //给微信用户推送返现活动模板消息
                ReferralActivityService::pushWXCashActivityTemplateMsg($params['msg_body']);
                break;
        }

        return HttpHelper::buildResponse($response, []);
    }

    public function tableSync(Request $request, Response $response)
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

        switch ($params['event_type']) {
            case TableSyncTopic::EVENT_TYPE_SYNC:
                TableSyncQueue::receive($params['msg_body']);
        }

        return HttpHelper::buildResponse($response, []);
    }

    /**
     * 天润语音回掉
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws \Exception
     */
    public function callCenter(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'event_type',
                'type' => 'required',
                'error_code' => 'event_type_is_required',
            ],

        ];
        $params = $request->getParams();
        $result = Valid::validate($params, $rules);

        if ($result['code'] == 1) {
            return $response->withJson($result, 200);
        }

        switch ($params['event_type']) {
            case VoiceCallTRService::CALLBACK_VOICECALL_COMPLETE:
                $voiceCall = new VoiceCallTRService(DictConstants::get(DictConstants::VOICE_CALL_CONFIG, 'tianrun_voice_call_host'));
                $voiceCall->setCallbackParams($params['data']);
                $voiceCall->saveCallbackResult();

            default:
        }

        return $response->withJson(['code' => 0], 200);
    }
}