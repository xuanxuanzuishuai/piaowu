<?php
/**
 * 清晨用户活动任务奖励信息表
 */

namespace App\Models;

use App\Libs\MysqlDB;

class MorningTaskAwardModel extends Model
{
    public static $table = 'morning_task_award';

    const MORNING_ACTIVITY_TYPE = 1;    // 清晨5日打卡活动

    /**
     * 获取学生5日打卡奖励信息
     * @param $studentUuid
     * @return array
     */
    public static function getStudentFiveDayAwardList($studentUuid)
    {
        $db = MysqlDB::getDB();
        $sql = "select * from " . self::$table . ' as a' .
            " where a.student_uuid='" . $studentUuid . "'" .
            " and a.activity_type=" . self::MORNING_ACTIVITY_TYPE;
        $list = $db->queryAll($sql);
        return is_array($list) ? $list : [];
    }
}