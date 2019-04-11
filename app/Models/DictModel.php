<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/8/15
 * Time: 下午12:22
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class DictModel extends Model
{
    private static $cacheKeyListPri = "dict_list_";
    public static $table = "dict";
    public static $redisExpire = 0;
    public static $redisDB;

    /**
     * 根据类型获取下拉列表
     * @param $type
     * @return mixed
     */
    public static function getList($type){
        if (empty($type)){
            return [];
        }
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($type, self::$cacheKeyListPri);
        $list = $redis->hgetall($cacheKey);
        if (empty($list)){
            // 重新获取并缓存到Redis
            $db = MysqlDB::getDB();
            $result = $db->select(self::$table, [
                'type',
                'key_name',
                'key_code',
                'key_value'
            ], [
                'type' => $type,
                'ORDER' => ['key_code']
            ]);
            foreach ($result as $item){
                $redis->hset($cacheKey, $item['key_code'], json_encode($item));
            }
        }else{
            ksort($list);
            $result = array_values($list);
            $result = array_map(function($v){
                return json_decode($v, true);
            }, $result);
        }
        return $result;
    }

    /**
     * 获取多个list
     * @param $types
     * @return mixed
     */
    public static function getListsByTypes($types){
        if (empty($types)){
            return [];
        }
        $result = [];
        foreach ($types as $type){
            $list = self::getList($type);
            $result[$type] = array_map(function($item){
                $arr = (array)$item;
                return [
                    'code' => $arr['key_code'],
                    'value' => $arr['key_value']
                ];
            }, $list);
        }
        return $result;
    }

    /**
     * 获取显示值
     * @param $type
     * @param $keyCode
     * @return mixed
     */
    public static function getKeyValue($type, $keyCode){
        if (empty($type) || $keyCode === null || $keyCode == ""){
            return "";
        }
        // 从缓存获取
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($type, self::$cacheKeyListPri);
        $item = $redis->hget($cacheKey, $keyCode);
        if (empty($item)){
            $db = MysqlDB::getDB();
            $keyValue = $db->get(self::$table, 'key_value', [
                'AND' => [
                    'type' => $type,
                    'key_code' => $keyCode
                ]
            ]);
            if(!empty($keyValue)){
                // 缓存失效并重新加载
                self::delCache($type, self::$cacheKeyListPri);
                self::getList($type);
            }
        }else{
            $item = json_decode($item, true);
            $keyValue = $item['key_value'];
        }

        return empty($keyValue) ? "" : $keyValue;
    }

    /**
     * 获取多个Key值
     * @param $type
     * @param array $keyCodes key值数组
     * @return array
     */
    public static function getKeyValuesByArray($type, $keyCodes){
        $result = [];
        foreach ($keyCodes as $keyCode) {
            $result[] = self::getKeyValue($type, $keyCode);
        }
        return $result;
    }

    /**
     * 添加字典值
     * @param $type
     * @param $keyCode
     * @param $keyValue
     * @param $typeName
     * @param $desc
     * @return mixed
     */
    public static function addKeyValue($type, $keyCode, $keyValue, $typeName = '', $desc = ''){
        if (empty($type)){
            // type不能为空
            return false;
        }
        $list = self::getList($type);
        if (empty($list) && empty($typeName)){
            // 该类型第一条数据要求必须添加typeName参数
            return false;
        }
        if (!empty($list)){
            // 已有列表数据，此接口忽略typeName参数，使用已有typeName
            $typeName = $list[0]['type_name'];
        }

        MysqlDB::getDB()->insert(self::$table, [
            'type' => $type,
            'key_name' => $typeName,
            'key_code' => $keyCode,
            'key_value' => $keyValue,
            'desc' => $desc
        ]);

        // 缓存失效
        self::delCache($type, self::$cacheKeyListPri);

        return self::getList($type);
    }

    /**
     * 删除字典值
     * @param $type
     * @param $keyCode
     * @return mixed
     */
    public static function delete($type, $keyCode){
        MysqlDB::getDB()->delete(self::$table, [
            'type' => $type,
            'key_code' => $keyCode
        ]);
        // 缓存失效
        self::delCache($type, self::$cacheKeyListPri);

        return self::getList($type);
    }

    /**
     * 更新字典值
     * @param $type
     * @param $keyCode
     * @param $keyValue
     * @return int|null
     */
    public static function updateValue($type, $keyCode, $keyValue)
    {
        $where = [
            'type' => $type,
            'key_code' => $keyCode,
        ];
        $db = MysqlDB::getDB();
        $result = $db->updateGetCount(self::$table, ['key_value'=>$keyValue], $where);
        self::delCache($type, self::$cacheKeyListPri);
        return $result;
    }

}