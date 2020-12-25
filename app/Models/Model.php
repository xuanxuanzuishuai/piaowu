<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/4/4
 * Time: 上午11:49
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;

class Model
{
    protected static $table = "";
    protected static $redisDB;
    protected static $redisExpire = 0;
    protected static $redisPri;
    protected static $defaultRdsReadOnlyInstance = MysqlDB::CONFIG_SLAVE;

    protected static function dbRO()
    {
        return MysqlDB::getDB(static::$defaultRdsReadOnlyInstance);
    }
    private static function getDefaultCacheKeyPri()
    {
        $pri = empty(static::$redisPri) ? static::$table : static::$redisPri;
        return "op_{$pri}_";
    }

    public static function createCacheKey($key, $pri = null)
    {
        $pri = empty($pri) ? self::getDefaultCacheKeyPri() : $pri;
        return $pri . $key;
    }

    /**
     * @param $id
     * @return mixed|null
     */
    public static function getById($id)
    {
        $ret = null;

        $db = MysqlDB::getDB();
        $redis = RedisDB::getConn(static::$redisDB);

        $cacheKey = self::createCacheKey($id);
        $res = $redis->get($cacheKey);

        $expireTime = static::$redisExpire > 0 ? static::$redisExpire : Util::TIMESTAMP_ONEWEEK;
        if (empty($res)) {
            $ret = $db->get(static::$table, '*', ['id' => $id]);
            if (!empty($ret)) {
                $redis->set($cacheKey, json_encode($ret));
                $expire = $_ENV['DEBUG_MODE'] ? 100 : $expireTime;
                if ($expire > 0) {
                    $redis->expire($cacheKey, $expire);
                }
            }
        } else {
            $redis->expire($cacheKey, $expireTime);
            $ret = json_decode($res, true);
        }

        return $ret;
    }

    /**
     * @param $id
     * @param $pri
     * @return int
     */
    public static function delCache($id, $pri = null)
    {
        /** @var Model $redisDB */
        $redis = RedisDB::getConn(static::$redisDB);
        return $redis->del([static::createCacheKey($id, $pri)]);
    }

    /**
     * 批量删除缓存
     * 通过where条件获取多个ID
     * @param $where
     * @param null $pri
     * @return int
     */
    public static function batchDelCache($where, $pri = null)
    {
        $db = MysqlDB::getDB();
        $redis = RedisDB::getConn(static::$redisDB);
        $idArr = $db->select(static::$table, 'id', $where);
        $prefix = static::createCacheKey('', $pri);
        $cacheKeys = array_map(function ($v) use ($prefix) {
            return $prefix . $v;
        }, $idArr);

        if (count($cacheKeys) > 0) {
            return $redis->del($cacheKeys);
        }
        return 0;
    }

    /**
     * 获取指定记录
     * @param $where
     * @param array $fields
     * @return mixed
     */
    public static function getRecord($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        $db = MysqlDB::getDB();
        return $db->get(static::$table, $fields, $where);
    }

    /**
     * 获取记录列表
     * @param       $where
     * @param array $fields
     * @return array
     */
    public static function getRecords($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        $db = MysqlDB::getDB();
        return $db->select(static::$table, $fields, $where);
    }

    /**
     * 更新记录内容
     * @param $id
     * @param $data
     * @return int|null
     */
    public static function updateRecord($id, $data)
    {
        $where = ['id' => $id];
        if (empty($data)) {
            return 0;
        }
        $db = MysqlDB::getDB();
        $cnt = $db->updateGetCount(static::$table, $data, $where);
        static::delCache($id);
        return $cnt;
    }

    /**
     * 更新多条记录内容
     * @param $data
     * @param $where
     * @return int|null
     */
    public static function batchUpdateRecord($data, $where)
    {
        if (empty($data)) {
            return 0;
        }
        $db = MysqlDB::getDB();
        self::batchDelCache($where);
        $cnt = $db->updateGetCount(static::$table, $data, $where);
        return $cnt;
    }

    /**
     * 添加数据
     * @param $data
     * @return int|mixed|null|string
     */
    public static function insertRecord($data)
    {
        if (empty($data)) {
            return 0;
        }
        $db = MysqlDB::getDB();
        return $db->insertGetID(static::$table, $data);
    }

    /**
     * 批量插入数据
     * @param $arr
     * @return bool
     */
    public static function batchInsert($arr)
    {
        if (empty($arr)) {
            return false;
        }
        $db = MysqlDB::getDB();
        $pdo = $db->insert(static::$table, $arr);
        return $pdo->errorCode() == \PDO::ERR_NONE;
    }

    /**
     * @param array $where
     * @param array $join
     * @param int $page
     * @param int $pageSize
     * @param bool $onlyCount
     * @param array $fields
     * @return array
     */
    public static function getPage($where, $page = -1, $pageSize = 0, $onlyCount = false, $fields = [], $join = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        $db = MysqlDB::getDB();
        $join = empty($join) ? null : $join;
        $order = $where['ORDER'];
        unset($where['ORDER']);

        if (empty($join)) {
            $cnt = $db->count(static::$table, $where);
        } else {
            $cnt = $db->count(static::$table, $join, '*', $where);
        }

        if ($onlyCount) {
            return [$cnt, []];
        }

        $where['ORDER'] = $order;

        if (!empty($page) && $page > 0 && !empty($pageSize) && $pageSize > 0 && empty($where['LIMIT'] && empty($where['limit']))) {
            $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        }

        if (!empty($join)) {
            $data = $db->select(static::$table, $join, $fields, $where);
        } else {
            $data = $db->select(static::$table, $fields, $where);
        }

        return [$cnt, $data];
    }

    /**
     * 获取数据行数
     * @param $where
     * @return number
     */
    public static function getCount($where)
    {
        $db = MysqlDB::getDB();
        $count = $db->count(static::$table, $where);
        return $count;
    }
    /**
     * 数据库名前缀的表
     * @return string
     */
    public static function getTableNameWithDb()
    {
        return  $_ENV['DB_NAME'] . '.' . static::$table;
    }
}