<?php

namespace App\Services\Queue;

class SaveTicketTopic extends BaseTopic
{
    const TOPIC_NAME = "save_ticket";
    const EVENT_GENERATE_TICKET = 'generate_ticket';


    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 生成Ticket
     * @param array $data
     * @return $this
     */
    public function genTicket(array $data)
    {
        $this->setEventType(self::EVENT_GENERATE_TICKET);
        $this->setMsgBody($data);
        return $this;
    }
}