<?php
/**
 * Created by PhpStorm.
 * User: fll
 * Date: 2018/11/5
 * Time: 8:17 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentBrushModel extends Model
{
    public static $table = 'student_brush';

    /**
     * @param $studentId
     * @return array|null
     * 根据学员ID获取其刷单账户列表
     */
    public static function getBrushList($studentId)
    {
        $studentBrush = self::$table;
        $student = StudentModel::$table;
        $studentLoginInfo = StudentLoginInfoModel::$table;
        $employee = EmployeeModel::$table;
        $collection = CollectionModel::$table;

        $sql = "SELECT
                    s.id as student_id,
                    s.name as student_name,
                    s.mobile,
                    sli.device_model,
                    sli.idfa,
                    sli.imei,
                    sli.android_id,
                    e.name as assistant_name,
                    c.name as collection_name,
                    s.allot_collection_time as join_class_time,
                    s.create_time as register_time
                FROM
                    {$studentBrush} AS sbo
                    INNER JOIN {$studentBrush} AS sbn ON sbo.student_id = {$studentId} AND sbo.brush_no = sbn.brush_no
                    LEFT JOIN {$studentLoginInfo} AS sli ON sli.student_id = sbn.student_id AND sli.has_review_course = 1
                    LEFT JOIN {$student} as s ON s.id = sbn.student_id
                    LEFT JOIN {$employee} as e ON s.assistant_id = e.id
                    LEFT JOIN {$collection} as c ON s.collection_id = c.id";

        $db = MysqlDB::getDB();
        return $db->queryAll($sql);
    }
}