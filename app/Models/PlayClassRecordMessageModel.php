<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/6
 * Time: 10:56 AM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class PlayClassRecordMessageModel extends Model
{
    public static $table = 'play_class_record_message';

    const STATUS_INIT = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILURE = 2;

    /**
     * @param $startTime
     * @param $endTime
     * @return number
     */
    public static function getCount($startTime, $endTime)
    {
        $db = MysqlDB::getDB();
        return $count = $db->count(self::$table, [
            'create_time[<>]' => [$startTime, $endTime],
            'status' => self::STATUS_INIT
        ]);
    }

    /**
     * @param $id
     * @return int|null
     */
    public static function delete($id)
    {
        $db = MysqlDB::getDB();
        return $db->deleteGetCount(self::$table, ['id' => $id]);
    }
}