<?php


namespace App\Services\Queue;


class ExchangePointsRedPackTopic extends BaseTopic
{
    const TOPIC_NAME = "exchange_points_red_pack";

    const SEND_RED_PACK = 'send_red_pack'; //发送红包

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发送红包
     * @param $data
     * @return $this
     */
    public function sendRedPack($data)
    {
        $this->setEventType(self::SEND_RED_PACK);
        $this->setMsgBody($data);
        return $this;
    }

}