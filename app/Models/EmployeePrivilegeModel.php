<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class EmployeePrivilegeModel extends Model
{
    const TYPE_INCLUDE = 1; // 附加权限
    const TYPE_EXCLUDE = 2; // 排除权限

    public static $table = "employee_privilege";
    public static $redisExpire = 0;
    public static $redisDB;

    /**
     * @param $userId
     * @return mixed|string
     */
    public static function getUserPrivileges($userId)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($userId);
        $res = $redis->get($cacheKey);
        if (empty($res)) {
            $res = MysqlDB::getDB()->select(self::$table, '*', ['employee_id' => $userId]);
            if (!empty($res))
                $redis->set($cacheKey, json_encode($res));
        } else {
            $res = json_decode($res, true);
        }
        return $res;
    }

    /**
     * 删除redis中的用户权限
     * @param $userId
     * @return int
     */
    public static function delUserPrivileges($userId)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($userId);
        return $redis->del($cacheKey);
    }

    /**
     * 删除用户权限
     * @param $userId
     * @param $type
     */
    public static function deleteUserPrivilege($userId, $type)
    {
        MysqlDB::getDB()->delete(self::$table, ['AND' => ['employee_id' => $userId, 'type' => $type]]);
        /** 删除redis中用户权限缓存 */
        self::delUserPrivileges($userId);
    }

    /**
     * 添加用户权限
     * @param $insert
     */
    public static function insertUserPrivileges($insert)
    {
        // 缓存在调用处已经删除，无需再次处理
        MysqlDB::getDB()->insert(self::$table, $insert);
    }
}