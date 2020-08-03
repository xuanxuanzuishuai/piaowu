<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/5/12
 * Time: 5:07 PM
 */

namespace App\Services\Queue;


class PushMessageTopic extends BaseTopic
{
    const TOPIC_NAME = "push_message";

    const EVENT_PUSH_WX = 'push_wx';
    //推送返现分享链接微信消息
    const EVENT_PUSH_WX_CASH_SHARE_MESSAGE = 'push_wx_cash_share_message';
    const EVENT_PUSH_SMS_TASK_REVIEW = 'push_sms_task_review';

    const EVENT_WX_PUSH_COMMON = 'wx_push_common'; // 微信消息推送


    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 给微信用户推送消息
     * @param $data
     * @param $eventType
     * @return $this
     */
    public function pushWX($data, $eventType = self::EVENT_PUSH_WX)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 发送短信课程点评
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function pushTaskReview($data, $eventType = self::EVENT_PUSH_SMS_TASK_REVIEW)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 微信推送公共接口
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function wxPushCommon($data, $eventType = self::EVENT_WX_PUSH_COMMON)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}