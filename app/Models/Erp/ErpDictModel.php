<?php

namespace App\Models\Erp;

use App\Libs\RedisDB;

class ErpDictModel extends ErpModel
{
    public static $table = 'erp_dict';

    private static $cacheKeyListPri = "erp_dict_list_";
    public static $redisExpire = 432000; // 12小时
    public static $redisDB;


    /**
     * 获取多个Key值
     * @param $type
     * @param array $keyCodes key值数组
     * @return array
     */
    public static function getKeyValuesByArray($type, $keyCodes)
    {
        $result = [];
        foreach ($keyCodes as $keyCode) {
            $result[] = self::getKeyValue($type, $keyCode);
        }
        return $result;
    }

    /**
     * 获取显示值
     * @param $type
     * @param $keyCode
     * @return mixed
     */
    public static function getKeyValue($type, $keyCode)
    {
        if (empty($type) || $keyCode === null || $keyCode == "") {
            return "";
        }
        // 从缓存获取
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($type, self::$cacheKeyListPri);
        $item = $redis->hget($cacheKey, $keyCode);
        if (empty($item)) {
            $keyValue = self::dbRO()->get(self::$table, 'key_value', [
                'AND' => [
                    'type' => $type,
                    'key_code' => $keyCode
                ]
            ]);
            if (!empty($keyValue)) {
                // 缓存失效并重新加载
                self::delCache($type, self::$cacheKeyListPri);
                self::getList($type);
            }
        } else {
            $item = json_decode($item, true);
            $keyValue = $item['key_value'];
        }
        return empty($keyValue) ? "" : $keyValue;
    }

    /**
     * 根据类型获取下拉列表
     * @param $type
     * @return mixed
     */
    public static function getList($type)
    {
        if (empty($type)) {
            return [];
        }
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($type, self::$cacheKeyListPri);
        $list = $redis->hgetall($cacheKey);
        if (empty($list)) {
            // 重新获取并缓存到Redis
            $result = self::dbRO()->select(self::$table, [
                'type',
                'key_name',
                'key_code',
                'key_value'
            ], [
                'type' => $type,
                'ORDER' => ['key_code']
            ]);
            foreach ($result as $item) {
                $redis->hset($cacheKey, $item['key_code'], json_encode($item));
            }
            $redis->expire($cacheKey, self::$redisExpire);
        } else {
            ksort($list);
            $result = array_values($list);
            $result = array_map(function ($v) {
                return json_decode($v, true);
            }, $result);
        }
        return $result;
    }

    /**
     * @param $id
     * @param $pri
     * @return int
     */
    public static function delCache($id, $pri = null)
    {
        $redis = RedisDB::getConn(static::$redisDB);
        return $redis->del([static::createCacheKey($id, $pri)]);
    }
}
