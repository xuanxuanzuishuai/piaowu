<?php
/**
 * Created by IntelliJ IDEA.
 * User: hemu
 * Date: 2019-04-29
 * Time: 15:16
 */

namespace App\Models;


class StudentAccountLogModel extends Model
{
    public static $table = "student_account_log";
    const TYPE_ADD = 1; // 入账
    const TYPE_REDUCE = 2; // 消费
    public static function insertSAL($studentAccountLogs) {
        return self::insertRecord($studentAccountLogs);
    }

    public static function getSALs($params,$page = -1 ,$count =20) {

    }
}