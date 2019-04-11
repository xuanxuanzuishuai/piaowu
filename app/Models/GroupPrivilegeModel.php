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

class GroupPrivilegeModel extends Model
{
    public static $table = "group_privilege";
    public static $redisExpire = 0;
    public static $redisDB;

    /**
     * @param $groupId
     * @return mixed|string
     */
    public static function getGroupPrivileges($groupId)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($groupId);
        $res = $redis->get($cacheKey);
        if (empty($res)) {
            $res = MysqlDB::getDB()->select(self::$table, '*', ['group_id' => $groupId]);
            if (!empty($res))
                $redis->set($cacheKey, json_encode($res));
        } else {
            $res = json_decode($res, true);
        }
        return $res;
    }

    /**
     * 删除redis中的组权限
     * @param $groupId
     * @return int
     */
    public static function delGroupPrivileges($groupId)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $cacheKey = self::createCacheKey($groupId);
        return $redis->del($cacheKey);
    }

    /**
     * 权限组详情
     * @param $groupId
     * @return array
     */
    public static function getPrivilegesByGroupId($groupId)
    {
        return MysqlDB::getDB()->select(self::$table, 'privilege_id', ['group_id' => $groupId]);
    }

    /**
     * 更新权限组权限
     * @param $groupId
     * @param $privilegeIds
     */
    public static function updateGroupPrivilege($groupId, $privilegeIds)
    {
        $db = MysqlDB::getDB();
        $result = $db->deleteGetCount(self::$table, ['group_id' => $groupId]);
        if (!empty($result)) {
            /** 删除redis中的缓存 */
            self::delGroupPrivileges($groupId);
        }

        $update = [];
        foreach ($privilegeIds as $privilegeId) {
            $update[] = ['group_id' => $groupId, 'privilege_id' => $privilegeId];
        }
        $db->insert(self::$table, $update);
    }
}