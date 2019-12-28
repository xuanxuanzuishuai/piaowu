<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/12/27
 * Time: 下午2:52
 */

namespace App\Models;


use App\Libs\RedisDB;

class StudentLandingModel
{
    private static $conn = null;

    const EXPIRE = 2 * 3600; // 2hours

    private static function getConn()
    {
        if(empty(self::$conn)) {
            self::$conn = RedisDB::getConn();
        }
        return self::$conn;
    }

    public static function setOpenId($uuid, $openId)
    {
        $conn = self::getConn();
        return $conn->setex($uuid . '.open_id', self::EXPIRE, $openId);
    }

    public static function getOpenId($uuid)
    {
        $conn = self::getConn();
        return $conn->get($uuid . '.open_id');
    }
}