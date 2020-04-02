<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/4/1
 * Time: 2:41 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class AIBillModel extends Model
{
    public static $table = 'ai_bill';

    public static function addBill($data)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    public static function getAutoApply($billId)
    {
        return MysqlDB::getDB()->get(self::$table, '*', [self::$table . '.bill_id' => $billId]);
    }
}