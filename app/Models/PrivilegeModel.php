<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/28
 * Time: 下午6:57
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;


class PrivilegeModel extends Model
{
    public static $table = "privilege";
    public static $redisExpire = 0;
    public static $redisDB;
    private static $cacheKeyHash = 'hash';


    /** 菜单 */
    const IS_MENU = 1;
    const NOT_MENU = 0;

    /** 状态 */
    const STATUS_CANCEL = 0;
    const STATUS_NORMAL = 1;

    /**
     * @param string $uri
     * @param string $method
     * @return mixed|string
     */
    public static function getPIdByUri($uri, $method)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey(self::$cacheKeyHash);
        $res = $redis->hget($cacheKey, $uri . "_" . $method);
        if (empty($res)) {
            $res = MysqlDB::getDB()->get(self::$table, '*', ['uri' => $uri, 'method' => $method]);
            if (!empty($res))
                $redis->hset($cacheKey, $uri . "_" . $method, json_encode($res));
        } else {
            $res = json_decode($res, true);
        }
        return $res;
    }

    /**
     * 权限列表
     * @param $where
     * @return array
     */
    public static function selectPrivileges($where)
    {
        $db = MysqlDB::getDB();
        $privileges = $db->select(self::$table, [
            '[>]' . self::$table . '(p)' => ['parent_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.status',
            self::$table . '.name',
            self::$table . '.uri',
            self::$table . '.created_time',
            self::$table . '.method',
            self::$table . '.is_menu',
            self::$table . '.menu_name',
            self::$table . '.parent_id',
            self::$table . '.unique_en_name',
            'p.name(parent_name)'
        ], $where);
        return $privileges;
    }

    /**
     * 一级菜单
     * @return array
     */
    public static function getFirstMenu()
    {
        $db = MysqlDB::getDB();
        $privileges = $db->select(self::$table, ['id', 'name'], [
            'AND' => [
                'is_menu' => self::IS_MENU,
                'parent_id' => 0,
                'status' => self::STATUS_NORMAL
            ]
        ]);
        return $privileges;
    }

    /**
     * 根据条件获取权限数量
     * @param $where
     * @return number
     */
    public static function getPrivilegeCount($where)
    {
        return MysqlDB::getDB()->count(self::$table, '*', $where);
    }

    /**
     * 添加权限
     * @param $insert
     * @return mixed
     */
    public static function insertPrivilege($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    /**
     * 更新权限
     * @param $id
     * @param $update
     * @return bool
     */
    public static function updatePrivilege($id, $update)
    {
        $result = self::updateRecord($id, $update);

        if ($result && $result > 0) {
            $privilege = self::getById($id);
            /** 删除redis中的缓存 */
            $redis = RedisDB::getConn(self::$redisDB);
            $cacheKey = self::createCacheKey(self::$cacheKeyHash);
            $redis->hdel($cacheKey, [$privilege['uri'] . '_' . $privilege['method']]);
            return true;
        }
        return false;
    }

    /**
     * 根据唯一标识获取
     * @param $privilege_en_name
     * @return mixed
     */
    public static function getByUniqEnName($privilege_en_name)
    {
        $privilege = MysqlDB::getDB()->get(self::$table, '*', ['unique_en_name' => $privilege_en_name]);
        return $privilege;
    }

    /**
     * 获取员工菜单
     * @param $privilegeIds
     * @return array
     */
    public static function getEmployeeMenu($privilegeIds)
    {
        $db = MysqlDB::getDB();
        $privileges = $db->select(self::$table, [
            'id', 'name', 'unique_en_name', 'parent_id', 'menu_name'
        ], [
            'is_menu' => self::IS_MENU,
            'id' => $privilegeIds,
            'status' => self::STATUS_NORMAL
        ]);
        return $privileges;
    }
}