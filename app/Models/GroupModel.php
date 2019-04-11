<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2018/7/2
 * Time: 下午6:47
 */

namespace App\Models;

use App\Libs\MysqlDB;

class GroupModel extends Model
{
    public static $table = "group";
    public static $redisExpire = 0;
    public static $redisDB;


    public static function getGroups()
    {
        return MysqlDB::getDB()->select(self::$table, '*', ["ORDER" => ["created_time" => "DESC"]]);
    }

    public static function insertGroup($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    public static function updateGroup($groupId, $update)
    {
        $db = MysqlDB::getDB();
        $result = $db->updateGetCount(self::$table, $update, ['id' => $groupId]);
        if ($result && $result > 0) {
            /** 删除redis中的缓存 */
            self::delCache($groupId);
            return true;
        }
        return false;
    }
}