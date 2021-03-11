<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/27
 * Time: 14:52
 */
namespace App\Models\Dss;


class DssAuthModel extends DssModel
{
    const TOKEN_LENGTH = 32;
    const TOKEN_LENGTH_MIN = 8;
    const TOKEN_LENGTH_MAX = 128;

    const TOKEN_EXPIRE_HOUR = 3600; // 1 hour
    const TOKEN_EXPIRE_SHORT = 1 * 86400; // 1 day
    const TOKEN_EXPIRE_MIDDLE = 7 * 86400; // 7 days
    const TOKEN_EXPIRE_LONG = 30 * 86400; // 30 days


    public static function randomToken($length = self::TOKEN_LENGTH)
    {
        if(!isset($length) || intval($length) <= self::TOKEN_LENGTH_MIN || intval($length) > self::TOKEN_LENGTH_MAX) {
            $length = self::TOKEN_LENGTH;
        }

        try {
            $token = bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            $token = md5(uniqid());
        }

        return $token;
    }
}