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

    const STATUS_STOP = 0; //解除绑定
    const STATUS_NORMAL = 1;  //绑定

    /** 获取老师管理的学生
     * @param $teacher_id
     * @return array
     */
    public static function getStudents($teacher_id) {
        $where = [self::$table .".teacher_id" => $teacher_id, self::$table .".status" => self::STATUS_NORMAL, "ORDER" => "org_id"];

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

    /**
     * 更新老师学生关系状态
     * @param $orgId
     * @param $teacherId
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateStatus($orgId, $teacherId, $studentId, $status)
    {
        $db         = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(self::$table, [
            'status'      => $status,
            'update_time' => time(),
        ], [
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
            'student_id' => $studentId,
        ]);
        return $affectRows;
    }
}