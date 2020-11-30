<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/4/4
 * Time: 上午11:49
 */

namespace App\Models\Dss;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class DssModel
{
    protected static $cacheKeyPri = "";
    protected static $table = "";
    protected static $redisDB;
    protected static $redisExpire = 3 * 86400;

    protected static $defaultRdsReadOnlyInstance = MysqlDB::CONFIG_SLAVE;

    protected static function dbRO()
    {
        return MysqlDB::getDB(static::$defaultRdsReadOnlyInstance);
    }

    public static function createCacheKey($key, $pri = null)
    {
        $pri = empty($pri) ? self::$cacheKeyPri : $pri;
        return $pri . $key;
    }

    /**
     * @param $id
     * @return mixed|null
     */
    public static function getById($id)
    {
        $ret = null;

        $redis = RedisDB::getConn(static::$redisDB);
        $cacheKey = static::createCacheKey($id);
        $res = $redis->get($cacheKey);

        if (empty($res)) {
            $ret = self::dbRO()->get(static::$table, '*', ['id' => $id]);
            if (!empty($ret)) {
                $redis->set($cacheKey, json_encode($ret));
                $expire = static::$redisExpire;
                if ($expire > 0) {
                    $redis->expire($cacheKey, $expire);
                }
            }
        } else {
            $ret = json_decode($res, true);
        }

        return $ret;
    }

    /**
     * 获取记录
     * @param $where
     * @param array $fields
     * @return mixed
     */
    public static function getRecords($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        return self::dbRO()->select(static::$table, $fields, $where);
    }

    /**
     * 获取指定单条记录
     * @param $where
     * @param array $fields
     * @return mixed
     */
    public static function getRecord($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        return self::dbRO()->get(static::$table, $fields, $where);
    }
    

    /**
     * 数据库名前缀的表
     * @return string
     */
    public static function getTableName()
    {
        return  $_ENV['DSS_DB_NAME'] . '.' . static::$table;
    }
}