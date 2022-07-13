<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Services\Queue\BaseTopic;
use App\Services\Queue\MessageReminder\MessageReminderTopic;
use App\Services\Queue\QueueService;
use Exception;

class LimitTimeAwardTopic extends BaseTopic
{
    const TOPIC_NAME                   = "limit_time_award";
    const EVENT_TYPE_SHARE_POSTER_AUTO_CHECK = 'share_poster_auto_check'; //自动审核海报

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
    public function nsqDataSet($data, $eventType): LimitTimeAwardTopic
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}