<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/3
 * Time: 11:04
 */

namespace App\Services\Queue;

class ThirdPartBillTopic extends BaseTopic
{
    const TOPIC_NAME = "third_part_bill";
    const EVENT_TYPE_IMPORT = 'import';

    /**
     * ThirdPartBillTopic constructor.
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    /**
     * 导入数据
     * @param $record
     * @return $this
     */
    public function import($record)
    {
        $this->setEventType(self::EVENT_TYPE_IMPORT);
        $this->setMsgBody($record);
        return $this;
    }
}