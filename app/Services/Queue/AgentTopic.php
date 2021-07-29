<?php

namespace App\Services\Queue;

/**
 * 代理相关业务消息队列topic
 * Class AgentTopic
 * @package App\Services\Queue
 */
class AgentTopic extends BaseTopic
{
    const TOPIC_NAME = "agent_business";

    //统计一级代理以及其下级的代理运营数据
    const STATIC_SUMMARY_DATA = 'static_summary_data';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 统计一级代理以及其下级的代理运营数据:推广订单，推广学员，二级代理数量
     * @param  $data
     * @return $this
     */
    public function staticSummaryData(array $data)
    {
        $this->setEventType(self::STATIC_SUMMARY_DATA);
        $this->setMsgBody($data);
        return $this;
    }
}