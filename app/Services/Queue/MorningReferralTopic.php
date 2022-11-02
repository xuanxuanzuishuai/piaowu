<?php
/**
 * 清晨转介绍topic
 */
namespace App\Services\Queue;

class MorningReferralTopic extends BaseTopic
{
    const TOPIC_NAME = "op_morning_referral";

    // 5日打卡 - day0 - 推送开班通知 - 开班通知发送公众号消息给学生
    const EVENT_WECHAT_PUSH_MSG_TO_STUDENT = 'wechat_open_collection_push_msg_to_student';
    // 5日打卡 -  day1~day3 - 邀请达标用户参与互动 - 微信公众号消息
    const EVENT_WECHAT_PUSH_MSG_JOIN_STUDENT = 'wechat_join_student_push_msg';
    // 5日打卡 - 发放红包
    const EVENT_CLOCK_ACTIVITY_SEND_RED_PACK = 'clock_activity_send_red_pack';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, self::CLUSTER_NSQ);
    }
}