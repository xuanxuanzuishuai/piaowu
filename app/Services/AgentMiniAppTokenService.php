<?php
namespace App\Services;

use App\Libs\RedisDB;

class AgentMiniAppTokenService
{
    const CACHE_KEY_TOKEN_PREFIX = "op_agent_token_";
    const USER_TOKEN_PREFIX = "op_user_token_";
    public static $redisExpire = 2592000; // 30 days
    /**
     * 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token)
    {
        return self::CACHE_KEY_TOKEN_PREFIX . $token;
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
        $userKey = self::getUserTokenKey($user_id, $user_type, $app_id, $open_id);
        $hasExistToken = $redis->get($userKey);
        if (!empty($hasExistToken)) {
            $token = self::getTokenInfo($hasExistToken);
            if ($token['open_id'] == $open_id) {
                return $hasExistToken;
            }
        }

        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $redis->setex($userKey, self::$redisExpire, $token);

        $key = self::getTokenKey($token);
        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id"   => $user_id,
            "user_type" => $user_type,
            "app_id"    => $app_id,
            "open_id"   => $open_id
        ]));
        return $token;
    }

    /**
     * 代理用户token缓存KEY
     * @param $userId
     * @param $userType
     * @param $appId
     * @param $openId
     * @return string
     */
    public static function getUserTokenKey($userId, $userType, $appId, $openId)
    {
        return self::USER_TOKEN_PREFIX . md5($userId.$userType.$appId.$openId);
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
    }

    /**
     * 刷新token过期时间
     * @param $token
     */
    public static function refreshUserToken($userId, $userType, $appId, $openId)
    {
        $key = self::getUserTokenKey($userId, $userType, $appId, $openId);
        $redis = RedisDB::getConn();
        $redis->expire($key, self::$redisExpire);
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
        $key = self::getTokenKey($token);
        RedisDB::getConn()->del([$key]);
    }
}