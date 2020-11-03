<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/11/01
 * Time: 2:33 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\UserCenter;

class StudentWeekReportModel extends Model
{
    public static $table = "student_week_report";

    /**
     * 查询微信推送消息数据
     * @param $year
     * @param $week
     * @return array|null
     */
    public static function getPushMessageData($year, $week)
    {
        $db = MysqlDB::getDB();
        $map = [
            ':year' => $year,
            ':week' => $week,
            ':app_id' => UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            ':user_type' => UserWeixinModel::USER_TYPE_STUDENT,
            ':busi_type' => UserWeixinModel::BUSI_TYPE_STUDENT_SERVER,
            ':status' => UserWeixinModel::STATUS_NORMAL,
        ];
        $data = $db->queryAll("SELECT
                                        swr.basic_info ->> '$.extra' as info,
                                        swr.start_time,
                                        swr.end_time,
                                        swr.id,
                                        uw.open_id
                                    FROM
                                        " . self::$table . " as swr
                                        INNER JOIN " . UserWeixinModel::$table . " as uw ON swr.student_id = uw.user_id
                                        
                                        AND uw.app_id = :app_id
                                        AND uw.user_type = :user_type
                                        AND uw.busi_type = :busi_type
                                        AND uw.status = :status
                                    WHERE
                                        swr.year = :year 
                                        AND swr.week = :week", $map);
        return $data;
    }
}
