<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/14
 * Time: 4:49 PM
 */

namespace App\Models;


use App\Libs\RedisDB;

class CountryCodeModel extends Model
{
    const CACHE_KEY = 'country_code_cache';
    const CACHE_EXPIRE = 86400;

    public static $table = "country_code";

    public static function updateCache()
    {
        $redis = RedisDB::getConn();
        $records = self::getRecords(['status' => 1]);
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
}