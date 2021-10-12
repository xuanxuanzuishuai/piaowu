<?php

namespace App\Services\Queue;

/**
 * 智能业务线学生粒子激活相关业务消息队列topic
 * Class AgentTopic
 * @package App\Services\Queue
 */
class StudentActiveTopic extends BaseTopic
{
    const TOPIC_NAME = "student_active";
    const STUDENT_LOGIN_ACTIVE = 'student_login_active';

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
        $this->setEventType(self::STUDENT_LOGIN_ACTIVE);
        $this->setMsgBody($data);
        return $this;
    }
}