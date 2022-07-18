<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Services\Queue\BaseTopic;
use App\Services\Queue\QueueService;
use Exception;

class LimitTimeAwardTopic extends BaseTopic
{
    const TOPIC_NAME                         = "limit_time_award";
    const EVENT_TYPE_SHARE_POSTER_AUTO_CHECK = 'share_poster_auto_check'; //自动审核海报
    const EVENT_TYPE_SEND_AWARD              = 'send_award'; // 发放奖励
    const EVENT_TYPE_PUSH_ACTIVITY_MSG       = 'push_activity_msg'; // 推送活动消息

    /**
     * 构造函数
     * @param null $publishTime
     * @throws Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime, QueueService::FROM_OP, true);
    }
}