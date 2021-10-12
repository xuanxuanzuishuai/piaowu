<?php

namespace App\Services\Queue;

/**
 * 真人业务线学生粒子激活相关业务消息队列topic
 * Class AgentTopic
 * @package App\Services\Queue
 */
class RealStudentActiveTopic extends BaseTopic
{
    const TOPIC_NAME = "lead_active";
    const LEAD_LOGIN_ACTIVE = 'lead_login_active';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 粒子激活
     * @param  $data
     * @return $this
     */
    public function studentLoginActive(array $data)
    {
        $this->setEventType(self::LEAD_LOGIN_ACTIVE);
        $this->setMsgBody($data);
        return $this;
    }
}