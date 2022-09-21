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
    //实物发货
    const EVENT_TYPE_DELIVER_MATERIAL_OBJECT = 'deliver_material_object';
    //用户已注册
    const EVENT_TYPE_STUDENT_REGISTERED = 'event_student_registered';
    //订单已支付
    const EVENT_TYPE_EVENT_ORDER_PAID = 'event_order_paid';

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
        $this->setEventType(self::EVENT_TYPE_STUDENT_REGISTERED);
        $this->setMsgBody($data);
        return $this;
    }
}