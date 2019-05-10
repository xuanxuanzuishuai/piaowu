<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/21
 * Time: 12:18
 */

namespace App\Models;

use App\Libs\MysqlDB;

/**
 *
 *
 * Class StudentAppModel
 * @package App\Models
 *
 */
class TeacherModelForApp extends Model
{
    const ENTRY_REGISTER = 1;
    const ENTRY_WAIT = 2;
    const ENTRY_ON = 3;
    const ENTRY_FROZEN = 4;
    const ENTRY_LEAVE = 5;
    const ENTRY_DISMISS = 6;
    const ENTRY_NO = 7;

    public static $table = 'teacher';
    public static $redisPri = "teacher";

    public static function getTeacherInfo($teacherID, $mobile)
    {
        if (empty($teacherID) && empty($mobile)) {
            return null;
        }

        $where = [];
        if (!empty($teacherID)) {
            $where[self::$table . '.id'] = $teacherID;
        }
        if (!empty($mobile)) {
            $where[self::$table . '.mobile'] = $mobile;
        }

        $db = MysqlDB::getDB();
        return $db->get(self::$table, [
            self::$table . '.id',
            self::$table . '.uuid',
            self::$table . '.mobile',
            self::$table . '.create_time',
            self::$table . '.status',
            self::$table . '.name',
            self::$table . '.thumb',
        ], $where);
    }

    /**
     * 获取机构下可用老师的列表
     * @param $orgId
     * @return array
     */
    public static function getTeacherNameByOrg($orgId)
    {
        $db = MysqlDB::getDB();
        $teachers = $db->select(TeacherOrgModel::$table . '(to)',
            [
                '[><]' . TeacherModel::$table . '(t)' => ['to.teacher_id' => 'id']
            ], ['t.id', 't.name'],
            [
                'to.org_id' => $orgId,
                'to.status' => TeacherOrgModel::STATUS_NORMAL,
                't.status' => StudentModel::STATUS_NORMAL
            ]);

        if (empty($teachers)) {
            return [];
        }
        return $teachers;
    }
}