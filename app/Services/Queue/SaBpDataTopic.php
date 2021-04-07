<?php

namespace App\Services\Queue;

use Exception;

class SaBpDataTopic extends BaseTopic
{
    const TOPIC_NAME = "sa_bp_data";

    const EVENT_POSTER_PUSH = 'ai_server_poster_push';  //海报主动推送
    const EVENT_ASSISTANT_SMS = 'ai_server_push_assistant'; // 给助教推送学员短信

    /**
     * SaBpDataTopic constructor.
     * @param null $publishTime
     * @throws Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 后台主动推送海报
     * @param $data
     * @return $this
     */
    public function posterPush($data): SaBpDataTopic
    {
        $this->setEventType(self::EVENT_POSTER_PUSH);
        $this->setMsgBody($data);
        return $this;
    }

    public function sendAssistantSms($data)
    {
        $this->setEventType(self::EVENT_ASSISTANT_SMS);
        $this->setMsgBody($data);
        return $this;
    }
}
