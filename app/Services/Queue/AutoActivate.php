<?php


namespace App\Services\Queue;



class AutoActivate extends BaseTopic
{
    const TOPIC_NAME = "acitve_gift_code";

    const CHECK_POSTER = 'delay_gift_code'; //审核通过-自动激活


    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }


    /**
     * 激活真人智能产品
     * @param $data
     * @return $this
     */
    public function checkAutoActivate($data)
    {
        $this->setEventType(self::CHECK_POSTER);
        $this->setMsgBody($data);
        return $this;
    }


}