<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2022/04/27
 * Time: 5:45 PM
 */

namespace App\Services\Queue;


/**
 * 抖店实物发货
 */
class DouStoreTopic extends BaseTopic
{
    const TOPIC_NAME = "dou_store";
    //用户已注册
    const EVENT_TYPE_THIRDPARTYORDER_STUDENTREGISTERED = 'event_thirdPartyOrder_studentRegistered';
    //订单已支付
    const EVENT_TYPE_THIRDPARTYORDER_PAID = 'event_thirdPartyOrder_paid';

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 抖店用户已注册
     * @param $data
     * @return $this
     */
    public function studentRegistered($data): DouStoreTopic
    {
        $this->setEventType(self::EVENT_TYPE_THIRDPARTYORDER_STUDENTREGISTERED);
        $this->setMsgBody($data);
        return $this;
    }
}