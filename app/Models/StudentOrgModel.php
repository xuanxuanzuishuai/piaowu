<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/4/19
 * Time: 上午9:54
 */

namespace App\Models;


use App\Libs\MysqlDB;

class StudentOrgModel extends Model
{
    const STATUS_STOP = 0; //解绑
    const STATUS_NORMAL = 1; //绑定
    public static $table = 'student_org';

    /**
     * 更新状态
     * @param $orgId
     * @param $studentId
     * @param $status
     * @return int|null
     */
    public static function updateStatus($orgId, $studentId, $status)
    {
        $db = MysqlDB::getDB();
        $affectRows = $db->updateGetCount(self::$table,[
            'status'      => $status,
            'update_time' => time(),
        ],[
            'org_id'     => $orgId,
            'student_id' => $studentId,
        ]);
        return $affectRows;
    }

    /**
     * 更新学员的CC
     * @param $studentId
     * @param $orgId
     * @param $ccId
     * @return int|null
     */
    public static function assignCC($studentId, $orgId, $ccId)
    {
        return MysqlDB::getDB()->updateGetCount(self::$table, [
            'cc_id' => $ccId,
            'update_time' => time()
        ], [
            'org_id'     => $orgId,
            'student_id' => $studentId
        ]);
    }

}