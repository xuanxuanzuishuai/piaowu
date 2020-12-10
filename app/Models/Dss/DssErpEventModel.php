<?php

namespace App\Models\Dss;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\Util;

class DssErpEventModel extends DssModel
{
    public static $table = 'erp_event';
    //缓存key前缀
    const EVENT_TASK_CACHE_PREFIX = 'event_task_';
    //有效期
    const CACHE_EXPIRE_TIME = Util::TIMESTAMP_THIRTY_DAYS;
    /**
     * 获取事件，任务数据
     * @param $eventId
     * @return array
     */
    public static function eventTaskData($eventId)
    {
        $db = MysqlDB::getDB(MysqlDB::CONFIG_SLAVE);
        $eventTaskData = $db->select(
            self::$table,
            [
                '[>]' . DssErpEventTaskModel::$table => ['id' => 'event_id'],
            ],
            [
                self::$table . '.id',
                self::$table . '.name',
                self::$table . '.desc',
                self::$table . '.settings',
                self::$table . '.start_time',
                self::$table . '.end_time',
                DssErpEventTaskModel::$table . '.id(task_id)',
                DssErpEventTaskModel::$table . '.condition',
                DssErpEventTaskModel::$table . '.award',
            ],
            [
                self::$table . '.id' => $eventId,
                self::$table . '.status' => Constants::STATUS_TRUE,
                self::$table . '.app_id' => Constants::SMART_APP_ID,
                DssErpEventTaskModel::$table . '.status' => Constants::STATUS_TRUE,
            ]);
        return $eventTaskData;
    }
}