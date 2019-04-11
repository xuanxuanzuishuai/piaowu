<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 18/9/5
 * Time: 上午9:54
 */

namespace App\Models;


use App\Libs\MysqlDB;

class EmployeeAdminModel extends Model
{
    public static $table = "employee_admin";
    public static $redisExpire = 0;
    public static $redisDB;

    /**
     * 获取一条数据
     * @param $fields
     * @param $where
     * @return mixed
     */
    public static function getRow($fields, $where)
    {
        return MysqlDB::getDB()->get(self::$table, $fields, $where);
    }

    /**
     * 插入到user_admin表
     * @param $insert
     * @return int|null|string
     */
    public static function insert($insert)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, $insert);
    }
}