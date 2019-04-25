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

    /**
     * 查找学生和老师除了指定teacherId外的绑定关系
     * @param $orgId
     * @param $studentId
     * @param $teacherId
     * @return mixed
     */
    public static function getRecordExceptTeacher($orgId, $studentId, $teacherId)
    {
        $record = self::getRecord([
            'org_id'        => $orgId,
            'student_id'    => $studentId,
            'teacher_id[!]' => $teacherId,
            'status'        => self::STATUS_NORMAL,
        ]);
        return $record;
    }

    /**
     * 解绑指定机构下指定学生与其已经绑定老师的绑定关系
     * @param $orgId
     * @param $studentId
     * @return int|null
     */
    public static function unbindTeacherStudentByStudent($orgId, $studentId)
    {
        $db = MysqlDB::getDB();
        return $db->updateGetCount(self::$table,[
            'status'      => self::STATUS_STOP,
            'update_time' => time(),
        ],[
            'org_id'     => $orgId,
            'student_id' => $studentId,
        ]);
    }

    /**
     * 解绑指定机构下指定老师与其已经绑定学生的绑定关系
     * @param $orgId
     * @param $teacherId
     * @return int|null
     */
    public static function unbindTeacherStudentByTeacher($orgId, $teacherId)
    {
        $db = MysqlDB::getDB();
        return $db->updateGetCount(self::$table,[
            'status'      => self::STATUS_STOP,
            'update_time' => time(),
        ],[
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ]);
    }
}