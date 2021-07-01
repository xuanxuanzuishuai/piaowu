<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/8
 * Time: 5:45 PM
 */

namespace App\Services\Queue;

class GiftCoursesTopic extends BaseTopic
{
    const TOPIC_NAME = "gift_courses";

    const EVENT_ACTIVITY_GIFT = 'activity_gift';

    /**
     * GiftCoursesTopic constructor.
     * @param null $publishTime
     * @param int $sourceAppId
     * @throws \Exception
     */
    public function __construct($publishTime = null, $sourceAppId = QueueService::FROM_OP)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime, $sourceAppId);
    }

    /**
     * 赠送时长
     * @param $data
     * @return $this
     */
    public function giftDuration($data)
    {
        $this->setEventType(self::EVENT_ACTIVITY_GIFT);
        $this->setMsgBody($data);
        return $this;
    }
}