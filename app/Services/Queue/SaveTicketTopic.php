<?php

namespace App\Services\Queue;

class SaveTicketTopic extends BaseTopic
{
    const TOPIC_NAME = "save_ticket";

    const EVENT_SEND_TICKET= 'send_ticket';
    const EVENT_GENERATE_TICKET = 'generate_ticket';
    const EVENT_PRE_GENERATE_QR_CODE = 'pre_generate_qr_code';


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

    /**
     * 预生成小程序码
     * @param array $data
     * @return $this
     */
    public function preGenQrCode(array $data)
    {
        $this->setEventType(self::EVENT_PRE_GENERATE_QR_CODE);
        $this->setMsgBody($data);
        return $this;
    }
}