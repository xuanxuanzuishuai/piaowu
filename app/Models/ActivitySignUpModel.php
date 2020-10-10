<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/09/28
 * Time: 3:49 PM
 */

namespace App\Models;

class ActivitySignUpModel extends Model
{
    static $table = 'activity_sign_up';
    //状态
    const STATUS_DISABLE = 0;//0无效
    const STATUS_ABLE = 1;//1有效
    const MIN_MILEAGES = 60;//最小练琴时长计数

    /**
     * 获取排行数据
     * @param $eventID
     * @param int $limit
     * @return array
     */
    public static function getRankData($eventID, $limit = 500)
    {
        return self::getRecords(
            [
                'event_id' => $eventID,
                'status' => self::STATUS_ABLE,
                'complete_mileages[>=]' => self::MIN_MILEAGES,
                "LIMIT" => [$limit],
                "ORDER" => ["complete_mileages" => "DESC", "complete_time" => "DESC"]
            ],
            [
                'user_id(student_id)',
                'complete_mileages(user_total_du)',
                'complete_time(comt)'
            ],
            false);
    }
}