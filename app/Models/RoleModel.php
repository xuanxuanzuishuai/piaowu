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

    const SUPER_ADMIN  = 1; //超级管理员
    const REGION_MANAGE = 2; //大区经理
    const BA_MANAGE = 3; //BA经理


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
       $result = self::updateRecord($id, $update);
       return ($result && $result > 0);
    }

    /**
     * 查询机构角色
     * @param $orgType
     * @return array
     */
    public static function selectByOrgType($orgType) {
        $where = ['ORDER' => ['created_time' => 'DESC']];
        if(!empty($orgType)) {
            $where['org_type'] = $orgType;
        }

        $db = MysqlDB::getDB();
        return $db->select(self::$table,'*', $where);
    }
}