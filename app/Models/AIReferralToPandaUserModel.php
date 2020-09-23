<?php

namespace App\Models;


use App\Libs\MysqlDB;


class AIReferralToPandaUserModel extends Model
{
    static $table = 'ai_referral_to_panda_user';

    const USER_TYPE_LT4D = 1; // 开班之日起7天内总有效练习天数小于4天
    const USER_TYPE_LT8D = 2; // 开班期内有效练习天数小于8天

    const USER_UNKNOWN_SUBSCRIBE = 0;  // 未关注
    const USER_IS_SUBSCRIBE = 1;       // 已关注

    const USER_NOT_SEND = 0; // 未发送
    const USER_IS_SEND = 1;  // 已发送

    const USER_SEND_SUCCESS = 1; // 发送成功
    const USER_SEND_FAILED = 2;  // 发送失败


    /**
     * 查询待发送模板消息用户信息
     * @param $userType
     * @param $date
     * @return array
     */
    public static function getToSentStudentInfo($userType, $date)
    {
        if (is_array($userType)) {
            $userType = implode(', ', $userType);
        }
        $db = MysqlDB::getDB();
        $sql = "SELECT 
       u.id,
       u.student_id,
       uw.open_id,
       s.mobile
    FROM 
         " . self::$table . " as u 
        INNER JOIN "
            . UserWeixinModel::$table . " as uw ON u.student_id = uw.user_id 
            AND uw.app_id = 8
            AND uw.user_type = 1
            AND uw.busi_type = 1
            AND uw.status = 1
        INNER JOIN "
            . StudentModel::$table . " as s ON s.id = uw.user_id
    WHERE 
        u.is_send=0
        AND u.is_subscribe = 0
        AND u.user_type in ( :user_type )
        AND u.create_time >= :start_time
        AND u.create_time <= :end_time;";

        $map = [
            ':user_type' => $userType,
            ':start_time' => strtotime($date),
            ':end_time' => strtotime($date . " 23:59:59"),
        ];
        $studentInfo = $db->queryAll($sql, $map);
        return $studentInfo ?? [];
    }
}