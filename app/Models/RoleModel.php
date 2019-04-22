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

    const IS_INTERNAL = 1; //内部角色
    const NOT_INTERNAL = 0; //非内部角色(机构角色)

    public static function getRoles()
    {
        return MysqlDB::getDB()->select(self::$table, '*', ['ORDER' => ['created_time' => 'DESC']]);
    }

    public static function insertRole($insert)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, $insert);
    }

    public static function updateRole($id, $update)
    {
       $result = self::updateRecord($id, $update,false);
       return ($result && $result > 0);
    }

    /**
     * 查询机构角色
     * @return array
     */
    public static function selectOrgRoles() {
        $db = MysqlDB::getDB();
        return $db->select(self::$table,'*',['is_internal' => self::NOT_INTERNAL, 'ORDER' => ['created_time' => 'DESC']]);
    }
}