<?php

namespace App\Services\Queue;

class SaveTicketTopic extends BaseTopic
{
    const TOPIC_NAME = "save_ticket";

    const EVENT_SEND_TICKET= 'send_ticket';


    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 后台自动生成ticket
     * @param array $data
     * @return $this
     */
    public function sendTicket(array $data)
    {
        $this->setEventType(self::EVENT_SEND_TICKET);
        $this->setMsgBody($data);
        return $this;
    }
}