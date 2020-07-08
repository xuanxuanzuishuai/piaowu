<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Models;

use App\Libs\MysqlDB;


class CheckInRecordModel extends Model
{
    public static $table = 'check_in_record';
    //活动类型
    const CONTINUE_CHECK_IN_ACTION_TYPE = 1;//连续签到

    /**
     * 获取学生连续签到天数
     * @param $studentId
     * @return bool|int
     */
    public static function studentCheckInDays($studentId)
    {
        //学生连续签到数据
        $lastDate = date("Y-m-d", strtotime("-1 day"));
        $continueCheckInSql = "SELECT
                                CASE
                                WHEN last_date >= '" . $lastDate . "' THEN days
                                ELSE 0
                                END AS continue_days,
                                last_date
                            FROM
                                " . CheckInRecordModel::$table . "
                            WHERE
                                student_id = " . $studentId . " AND type=" . self::CONTINUE_CHECK_IN_ACTION_TYPE;
        $continueCheckInData = MysqlDB::getDB()->queryAll($continueCheckInSql);
        $days = isset($continueCheckInData[0]['continue_days']) ? $continueCheckInData[0]['continue_days'] : 0;
        return $days;
    }
}