<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/5/12
 * Time: 5:07 PM
 */

namespace App\Services\Queue;


class PushMessageTopic extends BaseTopic
{
    const TOPIC_NAME = "push_message";

    const EVENT_PUSH_RULE_WX = 'push_rule_wx'; //自动推送微信

    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * @param $data
     * @param string $eventType
     * @return $this
     * 消息规则推送微信消息
     */
    public function pushRuleWx($data, $eventType = self::EVENT_PUSH_RULE_WX)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}