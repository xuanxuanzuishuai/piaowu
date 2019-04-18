<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;

use App\Libs\MysqlDB;


class TeacherStudentModel extends Model
{
    public static $table = "teacher_student";

    const STATUS_VALID = 1;

    /** 获取老师管理的学生
     * @param $teacher_id
     * @param $org_id
     * @return array
     */
    public static function getStudents($teacher_id) {
        $where = [self::$table .".teacher_id" => $teacher_id, self::$table .".status" => self::STATUS_VALID, "ORDER" => "org_id"];

        return MysqlDB::getDB()->select(self::$table, [
            '[><]' . StudentModel::$table => ["student_id" => "id"],
            '[><]' . OrganizationModel::$table => ["org_id" => "id"]
        ], [
            self::$table . ".student_id",
            StudentModel::$table . ".name",
            self::$table . ".org_id",
            OrganizationModel::$table . ".name(org_name)"
        ], $where);
    }
}