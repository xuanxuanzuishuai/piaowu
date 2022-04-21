<?php

namespace App\Services\Queue;

class GrantAwardTopic extends BaseTopic
{
    const TOPIC_NAME = "grant_award";

    const COUNTING_AWARD_TICKET= 'counting_activity_award';
    //计数任务物流信息更新
    const COUNTING_AWARD_LOGISTICS_SYNC= 'counting_award_logistics_sync';
    const EDIT_QUALIFIED = 'edit_qualified'; //更新达标期数
    const SIGN_UP = 'sign_up'; //报名
    const LOTTERY_GRANT_AWARD = 'lottery_grant_award'; //抽奖活动发放奖品

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 发放奖励
     * @param array $data
     * @return $this
     */
    public function countingAward(array $data)
    {
        $this->setEventType(self::COUNTING_AWARD_TICKET);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 更新物流信息以及发货信息
     * @param array $data
     * @return $this
     */
    public function countingSyncAwardLogistics(array $data)
    {
        $this->setEventType(self::COUNTING_AWARD_LOGISTICS_SYNC);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 更新达标期数
     * @param $data
     * @return $this
     */
    public function editQualified($data)
    {
        $this->setEventType(self::EDIT_QUALIFIED);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 报名操作
     * @param $data
     * @return $this
     */
    public function signUp($data)
    {
        $this->setEventType(self::SIGN_UP);
        $this->setMsgBody($data);
        return $this;
    }

    /**
     * 抽奖活动发送商品
     * @param $data
     * @return $this
     */
    public function lotteryGrantAward($data)
    {
        $this->setEventType(self::LOTTERY_GRANT_AWARD);
        $this->setMsgBody($data);
        return $this;
    }

}