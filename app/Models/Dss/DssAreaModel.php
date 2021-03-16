<?php
namespace App\Models\Dss;

use App\Libs\RedisDB;
use App\Libs\Util;

class DssAreaModel extends DssModel
{
    public static $table = "area";
    public static $redisExpire = 3600 * 30;
    public static $redisDB;

    /**
     * 根据条件获取记录
     * @param $code
     * @return array
     */
    public static function getRecordByCode($code)
    {
        $redis = RedisDB::getConn(static::$redisDB);
        $cacheKey = self::createCacheKey(self::$table, $code);
        $db = self::dbRO();
        $res = $redis->get($cacheKey);
        $expireTime = static::$redisExpire > 0 ? static::$redisExpire : Util::TIMESTAMP_ONEWEEK;
        if (empty($res)) {
            $where = ['code' => $code];
            $ret = $db->get(self::$table, ["code", "name", "fullname"], $where);
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
     * 根据条件获取记录
     * @param $parent_code
     * @return array
     */
    public static function getRecordsByParentCode($parent_code)
    {
        $where = [
            'parent_code' => $parent_code
        ];
        $db = self::dbRO();
        return $db->select(self::$table, ["code","name","fullname"], $where);
    }
}