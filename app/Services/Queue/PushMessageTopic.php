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
    const EVENT_PUSH_WX_CASH_SHARE_MESSAGE = 'push_wx_cash_share_message'; //推送返现分享链接微信消息
    const EVENT_PUSH_SMS_TASK_REVIEW = 'push_sms_task_review';
    const EVENT_WX_PUSH_COMMON = 'wx_push_common'; // 微信消息推送
    const EVENT_STUDENT_PAID = 'student_paid'; // 学生付费事件
    const EVENT_NEW_LEADS = 'new_leads'; // 新线索处理
    const EVENT_PUSH_RULE_WX = 'push_rule_wx'; //自动推送微信
    const EVENT_PUSH_MANUAL_RULE_WX = 'push_manual_rule_wx'; //手动推送微信
    const EVENT_COURSE_MANAGE_NEW_LEADS = 'course_manage_new_leads'; // 正式课新线索分配课管推送微信消息

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
     * 发送短信课程点评
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function pushTaskReview($data, $eventType = self::EVENT_PUSH_SMS_TASK_REVIEW)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 微信推送公共接口
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function wxPushCommon($data, $eventType = self::EVENT_WX_PUSH_COMMON)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    public function studentPaid($data, $eventType = self::EVENT_STUDENT_PAID)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    public function newLeads($data, $eventType = self::EVENT_NEW_LEADS)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 课管分配leads发送微信消息
     * @param $data
     * @param string $eventType
     * @return $this
     */
    public function courseManageNewLeadsPushWx($data, $eventType = self::EVENT_COURSE_MANAGE_NEW_LEADS)
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}