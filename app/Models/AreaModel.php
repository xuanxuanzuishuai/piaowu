<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/26
 * Time: 11:42 AM
 */
namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\Util;

class AreaModel extends Model
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
        $cacheKey = self::createCacheKey($code);
        $res = $redis->get($cacheKey);
        $expireTime = static::$redisExpire > 0 ? static::$redisExpire : Util::TIMESTAMP_ONEWEEK;
        if (empty($res)) {
            $where = ['code' => $code];
            $ret = MysqlDB::getDB()->get(self::$table, ["code","name","fullname"], $where);
            if (!empty($ret)){
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
        $result = MysqlDB::getDB()->select(self::$table, ["code","name","fullname"], $where);
        return $result;
    }
}