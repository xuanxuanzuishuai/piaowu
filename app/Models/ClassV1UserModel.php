<?php
/**
 * Created by PhpStorm.
 * User: zhushuangshuang
 * Date: 2019/9/26
 * Time: 11:53 AM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ClassV1UserModel extends Model
{
    public static $table = 'class_user_v1';

    const ROLE_STUDENT = 1;
    const ROLE_TEACHER = 2; // 老师
    const ROLE_T_MANAGER = 3; // 班主任

    const STATUS_NORMAL = 1;
    const STATUS_CANCEL = 0;

    public static function getClassUsers($classId, $role, $fields = [])
    {
        return self::getRecords([
            'class_id' => $classId,
            'status' => ClassV1UserModel::STATUS_NORMAL,
            'user_role' => $role
        ], $fields, false);
    }

    public static function getClassUserList($classId)
    {
        $sql = "select cu.user_id, cu.user_role, cu.id, cu.class_id, cu.create_time, t.name as teacher_name, s.name as student_name
               from " . self::$table . " as cu "
        . " left join " . StudentModel::$table . " as s on cu.user_id = s.id and cu.user_role = " . self::ROLE_STUDENT
        . " left join " . TeacherModel::$table . " as t on cu.user_id = t.id and cu.user_role in (" . self::ROLE_TEACHER . ", " . self::ROLE_T_MANAGER . ")"
        . " where cu.class_id = :class_id and cu.status = " . ClassV1UserModel::STATUS_NORMAL;
        $map[':class_id'] = $classId;
        return MysqlDB::getDB()->queryAll($sql, $map);
    }

    public static function abandonUser($classId, $sIds, $tIds)
    {
        if (!empty($sIds)) {
            ClassV1UserModel::batchUpdateRecord([
                'status' => ClassV1UserModel::STATUS_CANCEL
            ], [
                'class_id' => $classId,
                'status' => self::STATUS_NORMAL,
                'user_id' => $sIds,
                'user_role' => self::ROLE_STUDENT
            ], false);
        }

        if (!empty($tIds)) {
            ClassV1UserModel::batchUpdateRecord([
                'status' => ClassV1UserModel::STATUS_CANCEL
            ], [
                'class_id' => $classId,
                'status' => self::STATUS_NORMAL,
                'user_id' => $tIds,
                'user_role' => [self::ROLE_TEACHER, self::ROLE_T_MANAGER]
            ], false);
        }
    }

    public static function updatePosition($userId, $userRole, $classId, $position)
    {
        $db = MysqlDB::getDB();
        $count = $db->updateGetCount(self::$table, [
            'position'    => $position,
            'update_time' => time(),
        ], [
            'user_id'   => $userId,
            'class_id'  => $classId,
            'user_role' => $userRole,
        ]);
        return $count;
    }
}