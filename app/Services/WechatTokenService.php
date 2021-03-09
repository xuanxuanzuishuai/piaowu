<?php
namespace App\Services;

use App\Libs\RedisDB;

class WechatTokenService
{
    const cacheKeyTokenPri = "op_wechat_token_";
    const USER_TOKEN_KEY = 'op_user_token_';
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
     * @param $user_type
     * @param $app_id
     * @param $open_id
     * @return string
     */
    public static function generateToken($user_id, $user_type, $app_id, $open_id)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getUserTokenKey($user_id, $user_type, $app_id);
        $cache = $redis->get($cacheKey);
        if (!empty($cache)) {
            return $cache;
        }
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id" => $user_id,
            "user_type" => $user_type,
            "app_id" => $app_id,
            "open_id" => $open_id
        ]));
        $redis->setex($cacheKey, self::$redisExpire, $token);
        return $token;
    }

    /**
     * 用户token缓存，保持一个用户只生成一个token
     * @param $user_id
     * @param $user_type
     * @param $app_id
     * @param $open_id
     * @return string
     */
    public static function getUserTokenKey($user_id, $user_type, $app_id)
    {
        return self::USER_TOKEN_KEY . implode('_', [$app_id, $user_type, $user_id]);
    }

    /**
     * 根据token获取User Token(缓存)Key
     * @param $token
     * @return string
     */
    public static function getUserTokenKeyByToken($token)
    {
        $tokenInfo = json_decode(self::getTokenInfo($token), true);
        return self::getUserTokenKey($tokenInfo['user_id'], $tokenInfo['user_type'], $tokenInfo['app_id']);
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
        }
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

    public static function deleteToken($token)
    {
        $redis = RedisDB::getConn();
        $key = self::getTokenKey($token);
        $userKey = self::getUserTokenKeyByToken($token);
        $list = [$key];
        if (!empty($userKey)) {
            $list[] = $userKey;
        }
        $redis->del($list);
    }

    public static function delTokenByUserId($user_id, $user_type = null, $app_id = null)
    {
        $redis = RedisDB::getConn();
        $token = $redis->get(self::getUserTokenKey($user_id, $user_type, $app_id));
        if (!empty($token)) {
            self::deleteToken($token);
        }
        return true;
    }
}
