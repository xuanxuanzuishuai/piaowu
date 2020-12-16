<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/4/4
 * Time: 上午11:49
 */

namespace App\Models\Erp;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class ErpModel
{
    protected static $table = "";
    protected static $redisDB;
    protected static $redisExpire = 3 * 86400;

    protected static $defaultRdsReadOnlyInstance = MysqlDB::CONFIG_ERP_SLAVE;

    protected static function dbRO()
    {
        return MysqlDB::getDB(static::$defaultRdsReadOnlyInstance);
    }

    /**
     * @param $id
     * @return mixed|null
     */
    public static function getById($id)
    {
        return self::dbRO()->get(static::$table, '*', ['id' => $id]);
    }

    /**
     * 获取记录
     * @param $where
     * @param array $fields
     * @return mixed
     */
    public static function getRecords($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        return self::dbRO()->select(static::$table, $fields, $where);
    }

    /**
     * 获取指定单条记录
     * @param $where
     * @param array $fields
     * @return mixed
     */
    public static function getRecord($where, $fields = [])
    {
        if (empty($fields)) {
            $fields = '*';
        }
        return self::dbRO()->get(static::$table, $fields, $where);
    }


    /**
     * 数据库名前缀的表
     * @return string
     */
    public static function getTableNameWithDb()
    {
        return  $_ENV['DB_ERP_S_NAME'] . '.' . static::$table;
    }
}