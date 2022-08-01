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
        $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_ALL;
        if (in_array($userUuidInfo['status'], [2, 3])) {
            $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_TRAIL;
        } elseif (in_array($userUuidInfo['status'], [4, 5])) {
            $ruleStatus = MorningPushMessageService::MORNING_PUSH_USER_NORMAL;
        }
        // 消息推送体
        $data = [
            'open_id'      => $openId,
            'app_id'       => Constants::QC_APP_ID,
            'busi_type'    => Constants::QC_APP_BUSI_WX_ID,
            'student_uuid' => $userUuidInfo['uuid'] ?? '',
            'user_status'  => $userUuidInfo['status'],
            'student_id'   => $userUuidInfo['id'] ?? '',
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
        $data['rule_info']['content'] = json_decode($data['rule_info']['content'], true);
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
        //即时发送
        $res = MessageRecordLogModel::PUSH_SUCCESS;
        $wechat = WeChatMiniPro::factory($appId, $busiType);
        if (empty($wechat)) {
            SimpleLogger::error('wechat create fail', ['pushCustomMessage']);
            return false;
        }
        foreach ($messageRule['content'] as $item) {
            if (empty($item['value'])) {
                continue;
            }
            if ($item['type'] == WeChatConfigModel::CONTENT_TYPE_TEXT) { //发送文本消息
                //转义数据
                $content = Util::textDecode($item['value']);
                $content = Util::pregReplaceTargetStr($content, $data);
                $res1 = $wechat->sendText($data['open_id'], $content);
                //全部推送成功才算成功
                if (empty($res1) || !empty($res1['errcode'])) {
                    $res = MessageRecordLogModel::PUSH_FAIL;
                }
            } elseif ($item['type'] == WeChatConfigModel::CONTENT_TYPE_IMG) { //发送图片消息
                $posterImgFile = self::dealPosterByRule($data, $item);
                if (empty($posterImgFile)) {
                    SimpleLogger::error('empty poster file', ['pushCustomMessage', $data, $item]);
                    continue;
                }
                $wxData = $wechat->getTempMedia('image', $posterImgFile['unique'], $posterImgFile['poster_save_full_path']);
                //发送海报
                if (!empty($wxData['media_id'])) {
                    $res2 = $wechat->sendImage($data['open_id'], $wxData['media_id']);
                    //全部推送成功才算成功
                    if (empty($res2) || !empty($res2['errcode'])) {
                        $res = MessageRecordLogModel::PUSH_FAIL;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * @param $data
     * @param $item
     * @return array
     * 基于规则处理要发送的图片
     * @throws RunTimeException
     */
    private static function dealPosterByRule($data, $item)
    {
        $studentUuid = $data['student_uuid'] ?? '';
        $studentId = $data['student_id'] ?? 0;
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