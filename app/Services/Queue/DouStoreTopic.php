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

    /**
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 投递消息
     * @param $data
     * @param $eventType
     * @return $this
     */
    public function messageDelivery($data, $eventType): DouStoreTopic
    {
        $this->setEventType($eventType);
        $this->setMsgBody($data);
        return $this;
    }
}