<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/26
 * Time: 6:19 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ErpPackageModel extends Model
{
    public static $table = 'erp_package';

    public static function getPackAgeList($where)
    {
        return MysqlDB::getDB()->select(self::$table, ['id', 'name'], $where);
    }
}