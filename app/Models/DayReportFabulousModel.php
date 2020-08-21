<?php
namespace App\Models;


use App\Libs\MysqlDB;

class DayReportFabulousModel extends Model
{
    //表名称
    public static $table = "day_report_fabulous";

    public static function getTotalCount($studentId, $date)
    {
        $db = MysqlDB::getDB();
        return $db->count(self::$table, ['student_id' => $studentId, 'day_report_date' => $date]);
    }
}