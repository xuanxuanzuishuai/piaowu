<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/8/21
 * Time: 2:43 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ReportDataLogModel extends Model
{
    protected static $table = 'report_data_log';

    public static function getGradeRecord($studentId, $playGradeId)
    {
        $sql = 'select * from ' . self::$table . ' where student_id = :student_id and JSON_EXTRACT(report_data, \'$.play_grade_id\') = :play_grade_id';
        $map[':student_id'] = $studentId;
        $map[':play_grade_id'] = $playGradeId;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    public static function getChangeRecord($studentId, $changeType)
    {
        $sql = 'select * from ' . self::$table . ' where student_id = :student_id and JSON_EXTRACT(report_data, \'$.change_type\') = :change_type';
        $map[':student_id'] = $studentId;
        $map[':change_type'] = $changeType;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }
}