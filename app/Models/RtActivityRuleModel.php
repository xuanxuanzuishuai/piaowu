<?php
/**
 * rt_activity 表
 */

namespace App\Models;


use App\Libs\MysqlDB;

class RtActivityRuleModel extends Model
{
    public static $table = 'rt_activity_rule';

    const NOT_REGISTER = 1; //未注册
    const IS_REGISTER  = 2; //已注册

    public static function info($where, $fields = '*')
    {
        $db = MysqlDB::getDB();
        return $db->get(static::$table, $fields, $where);
    }
}