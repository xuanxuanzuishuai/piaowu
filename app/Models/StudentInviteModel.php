<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/6/28
 * Time: 下午4:52
 */

namespace App\Models;

use App\Libs\MysqlDB;

class StudentInviteModel extends Model
{
    const REFEREE_TYPE_STUDENT = 1; // 推荐人类型：学生
    const REFEREE_TYPE_AGENT = 2; // 推荐人类型：代理商
    public static $table = "student_invite";

    /**
     * 获取推荐人推荐学生数量
     * @param $refereeIds
     * @param $refereeType
     * @return array|null
     */
    public static function getReferralStudentCount($refereeIds, $refereeType)
    {
        $db = MysqlDB::getDB();
        $sql = 'SELECT
                    COUNT( student_id ) AS s_count,
                    referee_id 
                FROM
                    student_invite 
                WHERE
                    `referee_id` IN (' . $refereeIds . ') 
                    AND `referee_type` = ' . $refereeType . '
                GROUP BY
                    referee_id;';
        return $db->queryAll($sql);
    }
}