<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/21
 * Time: 4:26 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class GiftCodeModel extends Model
{
    const CODE_STATUS_NOT_REDEEMED = 0;
    const CODE_STATUS_HAS_REDEEMED = 1;
    const CODE_STATUS_INVALID = 2;

    const CODE_TIME_DAY = 1;
    const CODE_TIME_MONTH = 2;
    const CODE_TIME_YEAR = 3;

    public static $table = "gift_code";

    public static function getByCode($code)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['code' => $code]);
    }
}