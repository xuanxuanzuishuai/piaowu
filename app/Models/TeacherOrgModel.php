<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/17
 * Time: 下午9:04
 */

namespace App\Models;
use App\Libs\MysqlDB;

class TeacherOrgModel extends Model
{
    const STATUS_STOP = 0; // 解除绑定
    const STATUS_NORMAL = 1; // 绑定
    public static $table = 'teacher_org';

    public static function boundTeacher($org_id, $teacher_id)
    {
        $db     = MysqlDB::getDB();
        $orgObj = OrganizationModel::getById($org_id);
        if (empty($orgObj)) {
            return null;
        }
        $create_time = time();
        $id          = $db->insertGetID(self::$table, [
            "org_id"      => $org_id,
            "teacher_id"  => $teacher_id,
            "status"      => 1,
            "create_time" => $create_time,
            "update_time" => $create_time,
        ]);
        return $id;
    }

    /**
     * 更新老师和机构的绑定状态
     * @param $orgId
     * @param $teacherId
     * @param $status
     * @return int|null
     */
    public static function updateStatus($orgId, $teacherId, $status)
    {
        $db = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(self::$table,[
            'status'      => $status,
            'update_time' => time(),
        ],[
            'org_id'     => $orgId,
            'teacher_id' => $teacherId,
        ]);

        return $affectRows;
    }
}