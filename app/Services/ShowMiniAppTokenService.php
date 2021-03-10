<?php

namespace App\Services;

use App\Libs\RedisDB;

class ShowMiniAppTokenService
{
    const SHOW_OPEN_ID_TOKEN = "show_open_id_token_";
    const SHOW_TOKEN_TO_INFO = "show_token_to_info_";

    public static $redisExpire = 259200; // 3 days

    /**
     * 获取open_id缓存KEY
     * @param $openId
     * @return string
     */
    public static function getShowOpenIdTokenKey($openId)
    {
        return self::SHOW_OPEN_ID_TOKEN . $openId;
    }

    /**
     * 生成token
     * @param $open_id
     * @return string
     */
    public static function generateOpenIdToken($open_id)
    {
        $redis = RedisDB::getConn();
        $openIdToTokenKey = self::getShowOpenIdTokenKey($open_id);
        $hasExistToken = $redis->get($openIdToTokenKey);
        if (!empty($hasExistToken)) {
            return $hasExistToken;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $redis->setex($openIdToTokenKey, self::$redisExpire, $token);

        $tokenToOpenIdKey = self::getTokenKey($token);
        $redis->setex($tokenToOpenIdKey, self::$redisExpire, json_encode([
            "open_id"   => $open_id
        ]));

        return $token;
    }

    /**
     * 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token)
    {
        return self::SHOW_TOKEN_TO_INFO . $token;
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
}