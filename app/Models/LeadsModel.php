<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/08/30
 * Time: 1:14 PM
 */

namespace App\Models;

use App\Libs\RedisDB;

class LeadsModel extends Model
{

    //推送课管消息缓存key前缀
    const CACHE_KEY_PUSH_COURSE_MANAGE_STUDENTS = "push_course_manage_students_";

    /**
     * 设置推送课管消息缓存
     * @param $studentId
     * @param $time
     */
    public static function setPushCourseManageCache($studentId, $time)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::CACHE_KEY_PUSH_COURSE_MANAGE_STUDENTS . self::getCacheSuffix($time);
        $redis->hset($cacheKey, $studentId, $studentId);
        $redis->expire($cacheKey, strtotime("+3 days") - $time);
    }

    /**
     * 获取推送课管消息缓存
     * @param $time
     * @return array
     */
    public static function getPushCourseManageCache($time)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::CACHE_KEY_PUSH_COURSE_MANAGE_STUDENTS . date("Ymd", $time);
        return $redis->hgetall($cacheKey);
    }

    /**
     * 缓存后缀
     * @param $time
     * @return false|int
     */
    private static function getCacheSuffix($time)
    {
        /**
         * 微信消息推送规则
         * 1.当天12：00之前的信息,当天发送
         * 2.当天12：00之后的信息,第二天发送
         */
        $twelve = strtotime('today +12 hour');
        if ($time <= $twelve) {
            $date = date("Ymd");
        } else {
            //明天日期
            $date = date("Ymd", strtotime('+1 day'));
        }
        return $date;
    }
}