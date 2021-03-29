<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/24
 * Time: 5:45 PM
 */

namespace App\Services\Queue;

class DurationTopic extends BaseTopic
{
    const TOPIC_NAME = "operation_duration";

    const EVENT_SEND_DURATION = 'send_duration'; //发放时长


    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发放时长
     * @param $data
     * @return $this
     */
    public function sendDuration($data)
    {
        $this->setEventType(self::EVENT_SEND_DURATION);
        $this->setMsgBody($data);
        return $this;
    }
}