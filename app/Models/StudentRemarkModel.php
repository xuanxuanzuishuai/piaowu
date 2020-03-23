<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2020/3/20
 * Time: 3:45 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class StudentRemarkModel extends Model
{
    public static $table = 'student_remark';

    public static function addRemark($data)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    public static function getRemarksCount($studentId)
    {
        return MysqlDB::getDB()->count(self::$table, [self::$table . '.student_id' => $studentId]);
    }

    public static function selectRemarks($studentId, $page, $count)
    {
        return MysqlDB::getDB()->select(self::$table, [
            '[><]' . EmployeeModel::$table => ['employee_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.student_id',
            self::$table . '.remark_status',
            self::$table . '.remark',
            self::$table . '.create_time',
            self::$table . '.employee_id',
            EmployeeModel::$table . '.name(employee_name)'
        ], [
            'student_id' => $studentId,
            'ORDER' => ['create_time' => 'DESC'],
            'LIMIT' => [($page - 1) * $count, $count]
        ]);
    }
}