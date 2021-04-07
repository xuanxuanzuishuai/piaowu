<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/24
 * Time: 5:45 PM
 */

namespace App\Services\Queue;

class WechatTopic extends BaseTopic
{
    const TOPIC_NAME = "operation_wechat";

    const EVENT_UPDATE_USER_TAG = 'update_user_tag';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 更新用户标签
     * @param $data
     * @return $this
     */
    public function updateUserTag($data)
    {
        $this->setEventType(self::EVENT_UPDATE_USER_TAG);
        $this->setMsgBody($data);
        return $this;
    }
}