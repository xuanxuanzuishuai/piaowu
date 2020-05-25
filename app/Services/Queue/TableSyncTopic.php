<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/25
 * Time: 2:36 PM
 */

namespace App\Services\Queue;

class TableSyncTopic extends BaseTopic
{
    const TOPIC_NAME = "table_sync";
    const EVENT_TYPE_SYNC = 'sync';

    /**
     * TableSyncTopic constructor.
     * @param null $publishTime
     * @throws \Exception
     */
    public function __construct($publishTime = null)
    {
        parent::__construct(self::TOPIC_NAME, $publishTime);
    }

    public function sync($table, $id, $record)
    {
        $this->setEventType(self::EVENT_TYPE_SYNC);
        $this->setMsgBody([
            'table'         => $table,
            'id'        => $id,
            'record'        => $record,
        ]);

        return $this;
    }
}