<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/4/4
 * Time: 上午11:49
 */

namespace App\Models\Morning;
use App\Libs\MysqlDB;

class MorningModel
{
    protected static $table = "";
    protected static $redisDB;
    protected static $redisExpire = 3 * 86400;

    protected static $defaultRdsReadOnlyInstance = MysqlDB::CONFIG_MORNING_SLAVE;

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
        return  $_ENV['DB_DAWN_S_NAME'] . '.' . static::$table;
    }

    public static function createCacheKey($type, $pri)
    {
        return $type.$pri;
    }

	/**
	 * 获取数据行数
	 * @param $where
	 * @return number
	 */
	public static function getCount($where): int
	{
		$count = self::dbRO()->count(static::$table, $where);
		return (int)$count;
	}
}