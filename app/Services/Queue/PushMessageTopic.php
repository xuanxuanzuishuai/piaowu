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


    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 给微信用户推送消息
     * @param $data
     * @return $this
     */
    public function pushWX($data)
    {
        $this->setEventType(self::EVENT_PUSH_WX);
        $this->setMsgBody($data);
        return $this;
    }

}