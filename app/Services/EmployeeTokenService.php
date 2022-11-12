<?php
namespace App\Services;

use App\Libs\RedisDB;

class EmployeeTokenService
{
    const cacheKeyTokenPri = "token_with_employee_";
    const cacheUserTokenKeyPri = 'employee_with_token_';
    public static $redisExpire = 2592000; // 30 days
    /**
     * 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token) {
        return self::cacheKeyTokenPri . $token;
    }

    /**
     * 生成token
     * @param $employeeId
     * @return string
     */
    public static function generateToken($employeeId) {
        //当前用户是否已有已经在有效期的token
        $redis = RedisDB::getConn();
        $userKey = self::getUserTokenKey($employeeId);
        $hasExistToken = $redis->get($userKey);

        if (!empty($hasExistToken)) {
            return $hasExistToken;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $redis->setex($userKey, self::$redisExpire, $token);

        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "employee_id" => $employeeId,
        ]));
        return $token;
    }

    /**
     * 系统已经存在的当前用户的token
     * @param $employeeId
     * @return string
     */
    public static function getUserTokenKey($employeeId)
    {
        return self::cacheUserTokenKeyPri . '_' . $employeeId;
    }

    /**
     * 刷新用户token key过期时间
     * @param $employeeId
     */
    public static function refreshUserToken($employeeId)
    {
        $userKey = self::getUserTokenKey($employeeId);
        $redis = RedisDB::getConn();
        $redis->expire($userKey, self::$redisExpire);
    }

    /**
     * 清除token有效
     * @param $employeeId
     */
    public static function delUserTokenByUserId($employeeId)
    {
        $userKey = self::getUserTokenKey($employeeId);
        $redis = RedisDB::getConn();
        $redis->del([$userKey]);
    }

    /**
     * 刷新token过期时间
     * @param $token
     */
    public static function refreshToken($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn();
        $redis->expire($key, self::$redisExpire);
    }

    /**
     * @param $token
     * @return mixed|string
     */
    public static function getTokenInfo($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn();
        $ret = $redis->get($key);
        if (!empty($ret)) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public static function deleteToken($token) {
        $key = self::getTokenKey($token);
        RedisDB::getConn()->del([$key]);
    }
}