<?php

namespace App\Services\Queue;

class GrantAwardTopic extends BaseTopic
{
    const TOPIC_NAME = "grant_award";

    const COUNTING_AWARD_TICKET= 'counting_activity_award';


    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发放奖励
     * @param array $data
     * @return $this
     */
    public function countingAward(array $data)
    {
        $this->setEventType(self::COUNTING_AWARD_TICKET);
        $this->setMsgBody($data);
        return $this;
    }
}