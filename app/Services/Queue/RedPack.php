<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/5/8
 * Time: 5:45 PM
 */

namespace App\Services\Queue;


class RedPack extends BaseTopic
{
    const TOPIC_NAME = "red_pack";

    const SEND_RED_PACK = 'send_red_pack';

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