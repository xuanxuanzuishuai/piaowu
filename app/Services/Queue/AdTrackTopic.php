<?php
namespace App\Services\Queue;

class AdTrackTopic extends BaseTopic
{
    const TOPIC_NAME = "ad_track";

    const FORM_REGISTER = 'form_register'; //表单注册


    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }


    /**
     * 表单注册
     * @param $data
     * @return $this
     */
    public function formRegister($data)
    {
        $this->setEventType(self::FORM_REGISTER);
        $this->setMsgBody($data);
        return $this;
    }


}