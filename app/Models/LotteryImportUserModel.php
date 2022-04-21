<?php

namespace App\Models;

use App\Libs\MysqlDB;

class LotteryImportUserModel extends Model
{
    public static $table = 'lottery_import_user';

    /**
     * 返回导入获得的抽奖次数
     * @param $where
     * @return number
     */
    public static function getImportUserTimes($where)
    {
        $db = MysqlDB::getDB();
        $res = $db->sum(self::$table, ['rest_times'], $where);
        return $res ?: 0;
    }
}
