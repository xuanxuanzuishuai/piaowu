<?php
namespace App\Services;

use App\Libs\RedisDB;

class AppTokenService
{
    const cacheKeyTokenPri = "op_app_token_";
    const cacheUserTokenKeyPri = 'op_user_token_';
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
     * @param $user_id
     * @param $app_id
     * @return string
     */
    public static function generateToken($user_id, $app_id) {
        //当前用户是否已有已经在有效期的token
        $redis = RedisDB::getConn();
        $userKey = self::getUserTokenKey($user_id, $app_id);
        $hasExistToken = $redis->get($userKey);
        if (!empty($hasExistToken)) {
            return $hasExistToken;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $redis->setex($userKey, self::$redisExpire, $token);
        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id" => $user_id,
            "app_id" => $app_id,
        ]));
        return $token;
    }

    /**
     * 系统已经存在的当前用户的token
     * @param $userId
     * @param $appId
     * @return string
     */
    public static function getUserTokenKey($userId, $appId)
    {
        return self::cacheUserTokenKeyPri . $appId . '_' . $userId;
    }

    /**
     * 刷新用户token key过期时间
     * @param $userId
     * @param $appId
     */
    public static function refreshUserToken($userId, $appId)
    {
        $userKey = self::getUserTokenKey($userId, $appId);
        $redis = RedisDB::getConn();
        $redis->expire($userKey, self::$redisExpire);
    }

    /**
     * 清除token有效
     * @param $userId
     * @param $appId
     */
    public static function delUserTokenByUserId($userId, $appId)
    {
        $userKey = self::getUserTokenKey($userId, $appId);
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