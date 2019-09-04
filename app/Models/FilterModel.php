<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/9/3
 * Time: 4:32 PM
 */

namespace App\Models;


use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class FilterModel extends Model
{
    static $table = 'filter';

    static $flagCachePri = 'filter_flag_';

    public static function getByFlagId($flagId)
    {
        $redis = RedisDB::getConn();

        $key = self::$flagCachePri . $flagId;
        $cache = $redis->get($key);

        if (empty($cache)) {
            $records = self::getRecords([
                'flag_id' => $flagId,
                'status' => Constants::STATUS_TRUE,
            ], '*', false);

            $redis->set($key, json_encode($records));
        } else {
            $records = json_decode($cache, true);
        }

        return empty($records) ? [] : $records;
    }

    public static function updateRecord($id, $data, $isOrg = true)
    {
        $ret = parent::updateRecord($id, $data, $isOrg);

        $record = self::getById($id);
        $redis = RedisDB::getConn();
        $redis->del(self::$flagCachePri . $record['flag_id']);

        return $ret;
    }

    public static function countRecords($where)
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table, '*', $where);
    }
}