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
    const LEAD_LOGIN_ACTIVE = 'lead_login_active';//学生登陆激活统计
    const MAIN_COURSE_INTEND_ACTIVE = 'main_course_intend_active';//学生主课领取意向激活数据统计

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 学生登陆激活统计
     * @param  $data
     * @return $this
     */
    public function studentLoginActive(array $data)
    {
        $this->setEventType(self::LEAD_LOGIN_ACTIVE);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 学生主课领取意向激活数据统计
     * @param  $data
     * @return $this
     */
    public function mainCourseIntendActive(array $data)
    {
        $this->setEventType(self::MAIN_COURSE_INTEND_ACTIVE);
        $this->setMsgBody($data);
        return $this;
    }
}