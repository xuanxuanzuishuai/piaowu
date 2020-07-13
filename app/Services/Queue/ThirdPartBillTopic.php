<?php
/**
 * Created by PhpStorm.
 */

namespace App\Services\Queue;

class ThirdPartBillTopic extends BaseTopic
{
    const TOPIC_NAME = "third_part_bill";
    const EVENT_TYPE_IMPORT = 'import';

    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    public function import($record)
    {
        $this->setEventType(self::EVENT_TYPE_IMPORT);
        $this->setMsgBody($record);
        return $this;
    }
}