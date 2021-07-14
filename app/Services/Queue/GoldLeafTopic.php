<?php

namespace App\Services\Queue;

class GoldLeafTopic extends BaseTopic
{
    const TOPIC_NAME = "student_account_callback_referral";

    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发放金叶子
     * @param array $data
     * @return $this
     */
    public function grantGoldLeaf(array $data){

        $this->setEventType(1);
        $this->setMsgBody($data);
        return $this;
    }
}