<?php

namespace App\Services\Queue\MessageReminder;

use App\Services\Queue\BaseTopic;
use App\Services\Queue\QueueService;
use Exception;

class MessageReminderTopic extends BaseTopic
{
    const TOPIC_NAME = "op_message_reminder";
    const EVENT_TYPE_MESSAGE_REMINDER = 'message_reminder'; // 消息提醒
    const EVENT_TYPE_TASK_AWARD_MESSAGE_REMINDER = 'event_task_award_message_reminder'; // event task award 消息提醒

    /**
     * 构造函数
     * @param null $publishTime
     * @throws Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, true);
    }


    /**
     * 消息设定
     * @param $data
     * @param $eventType
     * @return $this
     */
    public function nsqDataSet($data, $eventType): MessageReminderTopic
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}