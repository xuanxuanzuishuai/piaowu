<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/15
 * Time: 下午3:48
 */

namespace App\Models;

//ClassroomAppModel封装了集体课缓存（登录，上课等）的读写，并不对应具体的数据库表，因此并不继承基础的Model类
use App\Libs\RedisDB;

class ClassroomAppModel
{
    private static $classroomTokenPrefix   = 'dss.classroom_app.classroom_token.';
    private static $scheduleTokenPrefix    = 'dss.classroom_app.schedule_token.';
    private static $scheduleTokenSetPrefix = 'dss.classroom_app.schedule_set.';

    private static $conn;

    private static function getConn()
    {
        if(empty($conn)) {
            self::$conn = RedisDB::getConn();
        }
        return self::$conn;
    }

    public static function setClassroomToken($token, $value)
    {
        $conn = self::getConn();
        $key = self::$classroomTokenPrefix . $token;
        return $conn->setex($key, 30 * 86400, json_encode($value, 1)); // one month
    }

    public static function getClassroomToken($token)
    {
        $conn = self::getConn();
        return $conn->get(self::$classroomTokenPrefix . $token);
    }

    public static function getScheduleSet($orgId)
    {
        $conn = self::getConn();
        $setKey = self::$scheduleTokenSetPrefix . $orgId;
        return $conn->smembers($setKey);
    }

    public static function getSchedule($token)
    {
        $conn = self::getConn();
        return $conn->get(self::$scheduleTokenPrefix . $token);
    }

    public static function removeScheduleSetMember($orgId, $token)
    {
        $conn = self::getConn();
        return $conn->srem(self::$scheduleTokenSetPrefix . $orgId, $token);
    }

    public static function setScheduleToken($token, $value)
    {
        $conn = self::getConn();
        return $conn->setex(self::$scheduleTokenPrefix . $token, 2 * 3600, json_encode($value, 1)); // 通常一节课不会超过2个小时
    }

    public static function addScheduleSet($orgId, array $token)
    {
        $conn = self::getConn();
        return $conn->sadd(self::$scheduleTokenSetPrefix . $orgId, $token);
    }

    public static function delScheduleToken($token)
    {
        $conn = self::getConn();
        return $conn->del(self::$scheduleTokenPrefix . $token);
    }
}