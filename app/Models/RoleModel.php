<?php
/**
 * Created by PhpStorm.
 * User: hemu
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;


use App\Libs\MysqlDB;

class RoleModel extends Model
{
    public static $table = "role";
    public static $redisExpire = 0;
    public static $redisDB;
    public static $superAdmin = -1;//超级管理员roleid

    public static function getRoles()
    {
        return MysqlDB::getDB()->select(self::$table, '*', ['ORDER' => ['created_time' => 'DESC']]);
    }

    public static function insertRole($insert)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $insert);
    }

    public static function updateRole($id, $update)
    {
       $result = self::updateRecord($id, $update);
       return ($result && $result > 0);
    }
}