<?php


namespace App\Services\Queue;


class UserPointsExchangeRedPackTopic extends BaseTopic
{
    const TOPIC_NAME = "points_exchange_red_pack";

    const SEND_RED_PACK = 'send_red_pack_from_points_exchange'; //发送红包 - 积分兑换
    const SEND_RED_PACK_SPEED = 'send_red_pack_from_points_exchange'; //更新红包领取进度 - 积分兑换

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

    /**
     * 更新红包领取进度
     * @param $data
     * @return $this
     */
    public function updateRedPackSpeed($data) {
        $this->setEventType(self::SEND_RED_PACK_SPEED);
        $this->setMsgBody($data);
        return $this;
    }

}