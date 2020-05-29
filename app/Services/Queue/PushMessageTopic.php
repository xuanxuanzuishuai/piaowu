<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/5/12
 * Time: 5:07 PM
 */

namespace App\Services\Queue;


class PushMessageTopic extends BaseTopic
{
    const TOPIC_NAME = "push_message";

    const EVENT_PUSH_WX = 'push_wx';
    //推送返现分享链接微信消息
    const EVENT_PUSH_WX_CASH_SHARE_MESSAGE = 'push_wx_cash_share_message';


    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 给微信用户推送消息
     * @param $data
     * @param $eventType
     * @return $this
     */
    public function pushWX($data, $eventType = self::EVENT_PUSH_WX)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

}