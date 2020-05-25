<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/21
 * Time: 3:06 PM
 */

namespace App\Libs;


use App\Services\Queue\TableSyncTopic;

class TableSyncQueue
{
    const EVENT_TYPE_SYNC = 'sync';

    const TABLE_WHITE_LIST = [
        'erp_package'
    ];

    /**
     * @param $table
     * @param $id
     * @param $record
     * @throws \Exception
     */
    public static function send($table, $id, $record)
    {
        $topic = new TableSyncTopic();
        $topic->sync($table, $id, $record)->publish();
    }

    public static function receive($message)
    {
        $db = MysqlDB::getDB();

        $record = $db->get($message['table'], '*', ['id' => $message['id']]);

        if (empty($record)) {
            $ret = $db->insertGetID($message['table'], $message['record']);
        } else {
            $ret = $db->updateGetCount($message['table'], $message['record'], ['id' => $message['id']]);
        }

        if (empty($ret)) {
            SimpleLogger::error('table sync receive error', [
                'message' => $message
            ]);
        }
    }
}