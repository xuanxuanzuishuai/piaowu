<?php

namespace App\Services;

use App\Libs\Constants;
use App\Libs\RedisDB;

class CommonWebTokenService
{
    const cacheKeyTokenPri = "op_common_web_wechat_token_";
    const USER_TOKEN_KEY = 'op_common_web_user_token_';
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
     * @param $user_id
     * @param $user_type
     * @param $app_id
     * @return string
     */
    public static function generateToken($user_id, $user_type, $app_id)
    {
        $redis = RedisDB::getConn();
        $cacheKey = self::getUserTokenKey($user_id, $user_type, $app_id);
        $cache = $redis->get($cacheKey);
        if (!empty($cache) && !empty(self::getTokenInfo($cache))) {
            return $cache;
        }
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id" => $user_id,
            "user_type" => $user_type,
            "app_id" => $app_id
        ]));
        $redis->setex($cacheKey, self::$redisExpire, $token);
        return $token;
    }

    /**
     * 用户token缓存，保持一个用户只生成一个token
     * @param $user_id
     * @param $user_type
     * @param $app_id
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
        $tokenInfo = self::getTokenInfo($token);
        if (!is_array($tokenInfo)) {
            $tokenInfo = json_decode($tokenInfo, true);
        }
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
        $userKey = self::getUserTokenKeyByToken($token);
        $list = [$key];
        if (!empty($userKey)) {
            $list[] = $userKey;
        }
        $redis->del($list);
    }

    public static function delTokenByUserId($user_id, $user_type = null, $app_id = Constants::SMART_APP_ID)
    {
        $redis = RedisDB::getConn();
        if (!is_array($app_id)) {
            $app_id = [$app_id];
        }
        $list = self::getUserTokenKeyPattern($user_id, $user_type, $app_id);
        if (!empty($list)) {
            $redis->del($list);
        }
        return true;
    }

    /**
     * 获取所有待删除token key
     * @param $user_id
     * @param null $user_type
     * @param null $app_id
     * @return array
     */
    public static function getUserTokenKeyPattern($user_id, $user_type, $app_id)
    {
        if (empty($user_id)) {
            return [];
        }
        $redis = RedisDB::getConn();
        $delKeys = [];
        $userKeys = [];

        $tmp = self::getUserTokenKey(
            $user_id,
            $user_type,
            $app_id
        );
        if (!isset($userKeys[$tmp])) {
            $delKeys[] = $tmp;
            $token = $redis->get($tmp);
            if (!empty($token)) {
                $delKeys[] = self::getTokenKey($token);
            }
            $userKeys[$tmp] = $tmp;
        }
        return $delKeys;
    }
}
