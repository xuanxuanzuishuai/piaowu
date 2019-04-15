<?php
/**
 * Created by PhpStorm.
 * User: lijie
 * Date: 2018/10/26
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class AppModel extends Model
{
    public static $table = "app";
    public static $redisExpire = 3600 * 8;
    public static $redisDB;
    // 应用类型缓存键名称
    private static $cacheKeyListPri = "app_list_app_type";

    /**
     * 应用名称
     * 1熊猫，2松鼠
     */
    const APP_PANDA = 1;
    const APP_SQUIRREL = 2;

    /**
     * 应用状态（1可用 0不可用）
     */
    const APP_STATUS_NORMAL = 1;
    const APP_STATUS_DEL = 0;

    /**
     * 获取所有记录
     * @return array
     */
    public static function getRecordsApp()
    {
        $where = [
            'status' => 1
        ];
        $db = MysqlDB::getDB();
        $result = $db->select(self::$table, [
            "id",
            "name",
            "remark"
        ], $where);
        return $result;
    }


    /**
     * 获取单条记录
     * @param int $appId
     * @return array
     */
    public static function getSingleRecord($appId)
    {
        $db = MysqlDB::getDB();
        // 是否存在相应的应用信息
        $appWhere = [
            'id' => $appId,
        ];
        $result = $db->get(self::$table, [
            "id",
            "name",
            "remark",
            "instrument",
            "type"
        ], $appWhere);
        return $result;
    }


    /**
     * 添加应用数据
     * @param $insert
     * @return int|mixed|null|string
     */

    public static function insertApp($insert) {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    /**
     * 根据应用名称获取记录
     * @param $name
     * @return mixed
     */
    public static function getRecordByName($name)
    {
        $where = [
            'name' => $name,
            'status' => 1
        ];
        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, [
            "id",
            "name",
            "remark",
            "instrument",
            "type"
        ], $where);
        return $result;
    }

    /**
     * 获取业务线名称
     * @param $appId
     * @return string
     */
    public static function getAppName($appId){
        $app = AppModel::getById($appId);
        return empty($app['name']) ? '' : $app['name'];
    }


    /**
     * 获取应用类型
     * @return array
     */
    public static function getAppTypeList() {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::$cacheKeyListPri;
        $list = $redis->hgetall($cacheKey);
        if (empty($list)) {
            // 从数据库中获取可用状态的应用
            $result =  AppModel::getRecords(['status' => self::APP_STATUS_NORMAL]);
           if(!empty($result)) {
               foreach ($result as $value) {
                   $redis->hset($cacheKey, $value['id'], json_encode($value));
               }
           }
        } else {
            ksort($list);
            $result = array_values($list);
            $result = array_map(function($v) {
                return json_decode($v, true);
            }, $result);
        }
        return $result;
    }


    /**
     * 获取应用类型（格式处理）
     * @param $type
     * @return array
     */
    public static function getAppTypeHandle($type) {
        $result = [];
        $list = self::getAppTypeList();
        $result[$type] = array_map(function ($arr) {
            return [
                'code'  => $arr['id'],
                'value' => $arr['name']
            ];
        }, $list);

        return $result;
    }

}