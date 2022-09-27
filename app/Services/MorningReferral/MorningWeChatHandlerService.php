<?php
/**
 * 微信回调接收处理
 * author: qingfeng.lian
 * date: 2022/7/29
 */

namespace App\Services\MorningReferral;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Morning;
use App\Libs\MorningDictConstants;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\MessagePushRulesModel;
use App\Models\MessageRecordLogModel;
use App\Models\MessageRecordModel;
use App\Models\PosterModel;
use App\Models\WeChatConfigModel;
use App\Services\MessageRecordService;
use App\Services\MessageService;
use App\Services\MiniAppQrService;
use App\Services\PosterService;
use App\Services\Queue\PushMessageTopic;
use Exception;

class MorningWeChatHandlerService
{
    /**
     * 用户关注公众号
     * @param $data
     * @return string
     */
    public static function subscribe($data)
    {
        // 推送关注消息
        try {
            (new PushMessageTopic)->pushRuleWx($data, PushMessageTopic::EVENT_WECHAT_MORNING_INTERACTION)->publish();
        } catch (Exception $e) {
            SimpleLogger::info('subscribe publish error', [$e->getMessage(), $data]);
        }
        return '';
    }

    /**
     * @param $data
     * @return string
     * @throws Exception
     */
    public static function menuClickEventHandler($data)
    {
        // 自定义KEY事件
        $keyEvent = $data['EventKey'];
        switch ($keyEvent) {
            case 'PUSH_MSG_USER_SHARE':
                /** 推荐好友 */
                (new PushMessageTopic)->pushRuleWx($data, PushMessageTopic::EVENT_WECHAT_MORNING_INTERACTION)->publish();
                break;
            default:
                SimpleLogger::info('menuClickEventHandler key event error', [$data]);
                break;
        }
        return '';
    }

    /**
     * 公众号推送消息的消费者
     * @param $msgBody
     * @return void
     * @throws RunTimeException
     */
    public static function interActionDealMessage($msgBody)
    {
        $openId = $msgBody['FromUserName'];
        $msgType = $msgBody['MsgType'];
        $event = $msgBody['Event'];
        $keyEvent = $msgBody['EventKey'];

        SimpleLogger::info('interActionDealMessage student weixin event: ', [$msgBody]);
        if ($msgType != 'event') {
            return;
        }
        // 根据open_id获取用户信息
        $userUuid = (new Morning())->getStudentUuidByOpenId([$openId])[$openId] ?? '';
        if (empty($userUuid) && $event != 'subscribe') {
            // 首关不检查是否绑定账号，非首关检查openid是否绑定了账号，未绑定账号引导绑定
            self::sendBindAccountMsg($openId);
            return;
        }
        // 根据用户信息获取用户状态
        $userUuidInfo = (new Morning())->getStudentList([$userUuid])[0] ?? [];
        // 根据用户状态获取需要推送的消息
        $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_ALL;
        if (in_array($userUuidInfo['status'], [Constants::MORNING_STUDENT_STATUS_NORMAL, Constants::MORNING_STUDENT_STATUS_NORMAL_EXPIRE])) {
            // 年卡，年卡过期
            $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_NORMAL;
        } elseif (in_array($userUuidInfo['status'], [Constants::MORNING_STUDENT_STATUS_TRAIL, Constants::MORNING_STUDENT_STATUS_TRAIL_EXPIRE])) {
            // 体验卡，体验卡过期
            $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_TRAIL;
        }
        // 消息推送体
        $data = [
            'open_id'      => $openId,
            'app_id'       => Constants::QC_APP_ID,
            'busi_type'    => Constants::QC_APP_BUSI_WX_ID,
            'student_uuid' => $userUuidInfo['uuid'] ?? '',
            'user_status'  => $userUuidInfo['status'],
            'channel_id'   => 0,
            'rule_info'    => [],
        ];
        // 获取规则id
        if ($event == 'subscribe') {
            /** 关注 */
            $data['rule_info'] = MessagePushRulesModel::getRuleInfo(Constants::QC_APP_ID, '首次关注', $ruleStatus);
        } elseif ($event == 'CLICK') {
            /** 自定义点击事件 */
            switch ($keyEvent) {
                case 'PUSH_MSG_USER_SHARE':
                    // 推荐好友
                    $data['rule_info'] = MessagePushRulesModel::getRuleInfo(Constants::QC_APP_ID, '推荐好友', $ruleStatus);
                    $data['channel_id'] = MorningDictConstants::get(MorningDictConstants::MORNING_REFERRAL_CONFIG, 'PUSH_MSG_USER_SHARE_CHANNEL_ID');
                    break;
                default:
                    break;
            }
        }
        // 推送规则不能为空
        if (empty($data['rule_info'])) {
            SimpleLogger::info('interActionDealMessage student weixin rule empty: ', [$msgBody, $data]);
            return;
        }
        self::sendWxMessage($data);
    }

    /**
     * 发送微信消息
     * @param $data
     * @param int $activityType
     * @return void
     * @throws RunTimeException
     */
    public static function sendWxMessage($data, $activityType = MessageRecordModel::ACTIVITY_TYPE_AUTO_PUSH)
    {
        $messageRule = $data['rule_info'];
        //发送客服消息
        if ($messageRule['type'] == MessagePushRulesModel::PUSH_TYPE_CUSTOMER) {
            $res = self::pushCustomMessage($messageRule, $data, $data['app_id'], $data['busi_type']);
            //推送日志记录
            MessageRecordService::addRecordLog($data['open_id'], $activityType, $data['rule_id'], $res);
        }
    }

    /**
     * @param $messageRule
     * @param $data
     * @param $appId
     * @param $busiType
     * @return bool
     * 基于规则 发送客服消息
     * @throws RunTimeException
     */
    private static function pushCustomMessage($messageRule, $data, $appId, $busiType)
    {
        SimpleLogger::info('pushCustomMessage params', [$messageRule, $data, $appId, $busiType]);
        return MessageService::pushCustomMessage($messageRule, $data, $appId, $busiType);
    }

    /**
     * @param $data
     * @param $item
     * @return array
     * 基于规则处理要发送的图片
     * @throws RunTimeException
     */
    public static function dealPosterByRule($data, $item)
    {
        $studentUuid = $data['student_uuid'] ?? '';
        $studentId = 0;
        $channelId = $data['channel_id'] ?? 0;
        $posterName = $item['name'] ?? ''; // message_push_rules表中name字段作为海报名称
        $config = DictConstants::getSet(DictConstants::TEMPLATE_POSTER_CONFIG);
        $extParams = [
            'poster_id'   => PosterModel::getIdByPath($item['value'], ['name' => $posterName]),
            'user_uuid'   => $studentUuid,
            'user_status' => $data['user_status'] ?? 0,
        ];
        $qrInfo = MiniAppQrService::getUserMiniAppQr(
            Constants::QC_APP_ID,
            Constants::QC_APP_BUSI_MINI_APP_ID,
            $studentId,
            Constants::USER_TYPE_STUDENT,
            $channelId,
            DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
            $extParams
        );
        return PosterService::generateQRPoster(
            $item['value'],
            $config,
            $studentId,
            Constants::USER_TYPE_STUDENT,
            $channelId,
            $extParams,
            $qrInfo
        );
    }

    /**
     * 发送绑定消息
     * @param $openId
     * @return void
     * @throws RunTimeException
     */
    public static function sendBindAccountMsg($openId)
    {
        // 没有绑定公众号，提示绑定账号
        list($openIdNotBindUserMsgId, $bindUserUrl) = MorningDictConstants::get(MorningDictConstants::MORNING_REFERRAL_CONFIG, [
            'OPEN_ID_NOT_BIND_USER_MSG_ID',
            'WX_BIND_USER_URL',
        ]);
        $res = self::pushCustomMessage(
            [
                    'content' => [
                        [
                            'type'  => WeChatConfigModel::CONTENT_TYPE_TEXT,
                            'value' => WeChatConfigModel::getRecord(['id' => $openIdNotBindUserMsgId])['content'],
                        ]
                    ]
            ],
            [
                'open_id'  => $openId,
                'jump_url' => $bindUserUrl,
            ],
            Constants::QC_APP_ID,
            Constants::QC_APP_BUSI_WX_ID
        );
        SimpleLogger::info('interActionDealMessage student weixin not bind user tip bind account: ', [$res]);
    }
}