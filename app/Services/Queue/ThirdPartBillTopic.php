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
    //第三放订单导入
    const EVENT_TYPE_IMPORT = 'import';
    //兑课用户导入
    const EVENT_TYPE_EXCHANGE_IMPORT = 'exchange_import';
    //兑课用户导入结束通知
    const EVENT_TYPE_EXCHANGE_IMPORT_FINISH = 'exchange_import_finish';

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

    /**
     * 兑课用户信息导入
     * @param $record
     * @return $this
     */
    public function exchangeImport($record)
    {
        $this->setEventType(self::EVENT_TYPE_EXCHANGE_IMPORT);
        $this->setMsgBody($record);
        return $this;
    }

    /**
     * 兑课用户信息导入结束
     * @param $data
     * @return $this
     */
    public function exchangeImportFinish($data)
    {
        $this->setEventType(self::EVENT_TYPE_EXCHANGE_IMPORT_FINISH);
        $this->setMsgBody($data);
        return $this;
    }
}