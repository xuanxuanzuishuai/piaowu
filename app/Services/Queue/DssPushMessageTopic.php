<?php
/**
 * Created by PhpStorm.
 * User: sunchanghui
 * Date: 2021-07-30 14:34:17
 * Time: 5:07 PM
 */

namespace App\Services\Queue;

class DssPushMessageTopic extends BaseTopic
{
    const TOPIC_NAME = "push_message";
    
    const EVENT_LANDING_RECALL = 'landing_recall'; //landing页召回发送消息到dss发送短信和语音
    
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }
    
    /**
     * 发放LandingRecall短信和语音
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function sendLandingRecall($data, $eventType = self::EVENT_LANDING_RECALL)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}
