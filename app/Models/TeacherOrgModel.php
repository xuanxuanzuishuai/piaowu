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

    /** 创建绑定关系
     * @param $org_id
     * @param $teacher_id
     * @return int
     */
    public static function createBoundInfo($org_id, $teacher_id)
    {
        $create_time = time();
        return MysqlDB::getDB()->insertGetID(self::$table, [
            "org_id"      => $org_id,
            "teacher_id"  => $teacher_id,
            "status"      => 1,
            "create_time" => $create_time,
            "update_time" => $create_time,
        ]);

    }

    /** 获取老师与该机构的绑定关系
     * @param $org_id
     * @param $teacher_id
     * @return mixed
     */
    public static function getBoundInfo($org_id, $teacher_id){
        $boundInfo = MysqlDB::getDB()->get(self::$table, ["id", "status"], [
            "org_id" => $org_id,
            "teacher_id" => $teacher_id
        ]);
        return $boundInfo;
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

    /**
     * 获取老师绑定机构列表
     * @param $teacherId
     * @param null $status
     * @return array|null
     */
    public static function getBoundList($teacherId, $status=null){
        $db = MysqlDB::getDB();
        $where = " where t_o.teacher_id=:teacher_id and o.status=" . OrganizationModel::STATUS_NORMAL;
        $map = ["teacher_id" => $teacherId];
        if ($status != null){
            $where = $where . " and t_o.status=:status";
            $map["status"] = $status;
        }
        $sql = "select t_o.org_id as org_id, o.name from " . self::$table . " as t_o inner join " . OrganizationModel::$table .
            " as o on o.id=t_o.org_id" . $where;

        $records = $db->queryAll($sql, $map);
        return $records;
    }
}