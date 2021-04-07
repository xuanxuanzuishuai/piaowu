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
    const TOPIC_NAME = "operation_message";

    const EVENT_PUSH_RULE_WX = 'push_rule_wx'; //自动推送微信
    const EVENT_PUSH_WX = 'push_wx';
    const EVENT_PUSH_MANUAL_RULE_WX = 'push_manual_rule_wx'; //手动推送微信
    const EVENT_PUSH_CHECKIN_MESSAGE = 'push_checkin_message'; //打卡海报审核消息

    const EVENT_WECHAT_INTERACTION = 'wechat_interaction';  // 微信交互
    const EVENT_USER_BIND_WECHAT   = 'bind_wechat';         // 绑定微信
    const EVENT_PAY_NORMAL         = 'pay_normal';          // 支付年卡
    const EVENT_SUBSCRIBE          = 'wechat_subscribe';    // 微信关注
    const EVENT_UNSUBSCRIBE        = 'wechat_unsubscribe';  // 微信取消关注
    const EVENT_START_CLASS        = 'start_class';         // 开班消息
    const EVENT_START_CLASS_SEVEN  = 'start_class_seven';   // 开班7天消息
    const EVENT_BEFORE_CLASS_ONE   = 'before_class_one_day';// 开班前1天消息
    const EVENT_BEFORE_CLASS_TWO   = 'before_class_two_day';// 开班前2天消息
    const EVENT_AFTER_CLASS_ONE    = 'after_class_one_day';// 结班后1天消息
    const EVENT_AIPL_PUSH = 'aipl_push'; // 智能陪练push
    const EVENT_MONTHLY_PUSH = 'monthly_push'; // 每月1号活动推送
    const EVENT_WEB_PAGE_CLICK = 'web_page_click'; // 召回页面按钮点击事件
    const EVENT_RECORD_USER_ACTIVE = 'record_user_active'; // 记录用户活跃


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
    /**
     * @param $data
     * @param string $eventType
     * @return $this
     * 基于规则手动推送微信消息
     */
    public function pushManualRuleWx($data, $eventType = self::EVENT_PUSH_MANUAL_RULE_WX)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
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

    /**
     * 智能陪练push
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function aiplPush($data, $eventType = self::EVENT_AIPL_PUSH)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 每月活动推送
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function monthlyPush($data, $eventType = self::EVENT_MONTHLY_PUSH)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 打卡海报审核消息
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function checkinMessage($data, $eventType = self::EVENT_PUSH_CHECKIN_MESSAGE)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 年卡召回页面消息（短信）
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function webPageMessage($data, $eventType = self::EVENT_WEB_PAGE_CLICK)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}
