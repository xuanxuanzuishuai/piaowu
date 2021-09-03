<?php

namespace App\Services\Queue;

class RealReferralTopic extends BaseTopic
{
    const TOPIC_NAME = "real_referral";
    
    const REAL_SEND_POSTER_AWARD = 'real_send_poster_award'; // 截图审核通过发奖
    const REAL_SHARE_POSTER_MESSAGE = 'real_share_poster_message';   // 推送分享海报审核消息
    
    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }
    
    /**
     * 真人 - 审核截图发送奖励
     * @param $data
     * @return $this
     */
    public function realSendPosterAward($data)
    {
        $this->setEventType(self::REAL_SEND_POSTER_AWARD);
        $this->setMsgBody($data);
        return $this;
    }
}
