<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/09/28
 * Time: 3:49 PM
 */

namespace App\Models;

use App\Libs\RedisDB;

class ActivitySignUpModel extends Model
{
    static $table = 'activity_sign_up';
    //状态
    const STATUS_DISABLE = 0;//0无效
    const STATUS_ABLE = 1;//1有效
    const MIN_MILEAGES = 60;//最小练琴时长计数
    //用户每日有效里程缓存key
    const DAILY_HALLOWEEN_RANK_CACHE_PRI_KEY = 'daily_halloween_rank_cache_';

    /**
     * 获取排行数据
     * @param $eventID
     * @return array
     */
    public static function getRankData($eventID)
    {
        return self::getRecords(
            [
                'event_id' => $eventID,
                'status' => self::STATUS_ABLE,
                'complete_mileages[>=]' => self::MIN_MILEAGES,
                "ORDER" => ["complete_mileages" => "DESC", "complete_time" => "ASC"]
            ],
            [
                'user_id(student_id)',
                'complete_mileages(user_total_du)',
                'complete_time(comt)'
            ],
            false);
    }


    /**
     * 获取每日有效时长缓存key
     * @param $date
     * @return string
     */
    private static function getDailyValidDurationCacheKey($date)
    {
        return self::DAILY_HALLOWEEN_RANK_CACHE_PRI_KEY . $date;
    }

    /**
     * 设置每日有效时长
     * @param $studentId
     * @param $stepDuration
     * @param $date
     */
    public static function setDailyValidDurationCache($studentId, $stepDuration, $date)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getDailyValidDurationCacheKey($date);
        $redis->zincrby($cacheKey, $stepDuration, $studentId);
    }

    /**
     * 获取每日有效时长
     * @param $studentId
     * @param $date
     * @return string
     */
    public static function getDailyValidDurationCache($studentId, $date)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getDailyValidDurationCacheKey($date);
        if ($redis->zrank($cacheKey, $studentId) === null) {
            return null;
        }
        return $redis->zscore($cacheKey, $studentId);

    }

    /**
     * 初始化每日有效时长
     * @param $studentId
     * @param $date
     * @param $duration
     */
    public static function initDailyValidDurationCache($studentId, $date, $duration)
    {
        $redis = RedisDB::getConn();
        $time = time();
        $cacheKey = self::getDailyValidDurationCacheKey($date);
        $redis->zadd($cacheKey, [$studentId => $duration]);
        $expireTime = strtotime('+2 days') - $time;
        $redis->expire($cacheKey, $expireTime);
    }

    /**
     * 获取排行榜中学生有效里程
     * @param $cacheKey
     * @param $studentId
     * @return string
     */
    public static function getStudentHalloweenMileages($cacheKey, $studentId){
        $redis = RedisDB::getConn();
        return $redis->hget($cacheKey, $studentId);
    }
}