<?php

namespace App\Services;

use App\Libs\RedisDB;

class WechatTokenService
{
    const cacheKeyTokenPri = "wechat_token_";
    const USER_TOKEN_KEY = 'user_token_';
    public static $redisExpire = 2592000; // 30 days

    /**
     * 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token)
    {
        return self::cacheKeyTokenPri . $token;
    }

    /**
     * 生成token
     * @return string
     */
    public static function generateToken($baId, $open_id)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getUserTokenKey($baId, $open_id);
        $cache = $redis->get($cacheKey);
        if (!empty($cache) && !empty(self::getTokenInfo($cache))) {
            return $cache;
        }
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "ba_id" => $baId,
            "open_id" => $open_id,
        ]));
        $redis->setex($cacheKey, self::$redisExpire, $token);
        return $token;
    }

    /**
     * 用户token缓存，保持一个用户只生成一个token
     */
    public static function getUserTokenKey($baId, $openId)
    {
        return self::USER_TOKEN_KEY . implode('_', [$baId, $openId]);
    }

    /**
     * 根据token获取User Token(缓存)Key
     * @param $token
     * @return string
     */
    public static function getUserTokenKeyByToken($token)
    {
        $tokenInfo = self::getTokenInfo($token);
        if (!is_array($tokenInfo)) {
            $tokenInfo = json_decode($tokenInfo, true);
        }
        return self::getUserTokenKey($tokenInfo['user_id'], $tokenInfo['user_type'], $tokenInfo['app_id'], $tokenInfo['open_id']);
    }


    /**
     * 刷新token过期时间
     * @param $token
     */
    public static function refreshToken($token)
    {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn();
        $redis->expire($key, self::$redisExpire);
        $userKey = self::getUserTokenKeyByToken($token);
        if (!empty($userKey)) {
            $redis->expire($userKey, self::$redisExpire);
            $redis->expire(self::getTokenKey($token), self::$redisExpire);
        }
    }

    /**
     * @param $token
     * @return mixed|string
     */
    public static function getTokenInfo($token)
    {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn();
        $ret = $redis->get($key);
        if (!empty($ret)) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public static function deleteToken($token)
    {
        $redis = RedisDB::getConn();
        $key = self::getTokenKey($token);
        $redis->del([$key]);
    }
}
