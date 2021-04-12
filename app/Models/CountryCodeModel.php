<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/14
 * Time: 4:49 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class CountryCodeModel extends Model
{
    const CACHE_KEY = 'country_code_cache';
    const CACHE_EXPIRE = 86400;
    const SELECT_STATUS = 1;

    public static $table = "country_code";

    public static function updateCache()
    {
        $table = self::$table;
        $status = self::SELECT_STATUS;

        $redis = RedisDB::getConn();
        $db = MysqlDB::getDB();
        $records = $db->queryAll("select * from {$table} where status = {$status} ORDER BY pinyin ASC");
        $cache = json_encode($records);
        $redis->setex(self::CACHE_KEY, self::CACHE_EXPIRE, $cache);
        return $records;
    }

    public static function getAll()
    {
        $redis = RedisDB::getConn();
        $cache = $redis->get(self::CACHE_KEY);

        if (empty($cache)) {
            $cache = self::updateCache();
        } else {
            $cache = json_decode($cache, true);
        }

        return $cache ?? [];
    }

    /**
     * 通过指定的排序规则获取数据
     * @param $sortColumnRule
     * @return array|null
     */
    public static function getDataSpecifySort($sortColumnRule)
    {
        $db = MysqlDB::getDB();
        $orderRule = " pinyin ASC";
        if (!empty($sortColumnRule)) {
            $orderRule = $sortColumnRule . "," . $orderRule;
        }
        return $db->queryAll("select * from ".self::$table." where status = ".self::SELECT_STATUS." ORDER BY  ".$orderRule);
    }
}