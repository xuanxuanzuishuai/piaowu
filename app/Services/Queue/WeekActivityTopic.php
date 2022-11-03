<?php
/**
 * 周周领奖白名单操作
 */

namespace App\Services\Queue;

class WeekActivityTopic extends BaseTopic
{
    const TOPIC_NAME = "week_activity";
    const EVENT_WHITE_GRANT_RED_PKG  = 'white_grant_leaf_to_red_pkg';//白名单用户发送金叶子到红包
    const EVENT_GET_WHITE_GRANT_STATUS = 'get_white_grant_status'; //获取白名单用户发放状态
    const EVENT_ACTIVITY_ENABLE_STATUS_EDIT = 'activity_enable_status_edit'; // 周周领奖活动启用状态修改

    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 白名单用户发放金叶子
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function weekWhiteGrandLeaf($data, $eventType = self::EVENT_WHITE_GRANT_RED_PKG)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }


    /**
     * 获取白名单账户红包发放状态
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function getWeekWhiteSendRedPkgStatus($data, $eventType = self::EVENT_GET_WHITE_GRANT_STATUS){
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 活动状态变更
     * @param $data
     * @param $eventType
     * @return $this
     */
    public function activityEnableStatusEdit($data, $eventType = self::EVENT_ACTIVITY_ENABLE_STATUS_EDIT){
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

}
