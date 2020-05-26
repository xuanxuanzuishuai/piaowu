<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/5/26
 * Time: 6:22 PM
 */

namespace App\Models;


use App\Libs\SimpleLogger;

class ReadonlyModel extends Model
{
    public static function insertRecord($data, $isOrg = true)
    {
        static::readonlyError();
        return null;
    }

    public static function batchInsert($arr, $isOrg = true)
    {
        static::readonlyError();
        return null;
    }

    public static function updateRecord($id, $data, $isOrg = true)
    {
        static::readonlyError();
        return null;
    }

    public static function batchUpdateRecord($data, $where, $isOrg = true)
    {
        static::readonlyError();
        return null;
    }

    private static function readonlyError()
    {
        SimpleLogger::error("read only model", ['table' => static::$table]);
    }
}