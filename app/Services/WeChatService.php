<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/19
 * Time: 18:08
 */

namespace App\Services;

use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use GuzzleHttp\Client;


class WeChatService
{
    const cacheKeyTokenPri = "wechat_token_";

    const USER_TYPE_STUDENT = 1;
    const USER_TYPE_TEACHER = 2;

    /**
     * 微信网页授权相关API URL
     */
    protected static $weixinHTML5APIURL = 'https://api.weixin.qq.com/sns/';

    public static $redisExpire = 2592000; // 30 days

    protected static $redisDB;

    /** 获取token key
     * @param $token
     * @return string
     */
    public static function getTokenKey($token) {
        return self::cacheKeyTokenPri . $token;
    }

    /** 生成token
     * @param $user_id
     * @param $user_type
     * @param $app_id
     * @param $open_id
     * @return string
     */
    public static function generateToken($user_id, $user_type, $app_id, $open_id) {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $key = self::getTokenKey($token);

        $redis = RedisDB::getConn(self::$redisDB);
        $redis->setex($key, self::$redisExpire, json_encode([
            "user_id" => $user_id,
            "user_type" => $user_type,
            "app_id" => $app_id,
            "open_id" => $open_id
        ]));
        return $token;
    }

    /** 刷新token过期时间
     * @param $token
     */
    public static function refreshToken($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $redis->expire($key, self::$redisExpire);
    }

    /**
     * @param $token
     * @return mixed|string
     */
    public static function getTokenInfo($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $ret = $redis->get($key);
        if (!empty($ret)) {
            $ret = json_decode($ret, true);
        }
        return $ret;
    }

    public static function deleteToken($token) {
        $key = self::getTokenKey($token);
        $redis = RedisDB::getConn(self::$redisDB);
        $redis->expire($key, 0);
    }

    /**
     * 根据用户授权获得的code换取用户open id 和access id
     * 参考: 微信网页授权"snsapi_base" 通过redirect回传给当前server指定的url并附带code参数
     */
    public static function getWeixnUserOpenIDAndAccessTokenByCode($code)
    {

        $client = new Client(['base_uri' => self::$weixinHTML5APIURL]);

        $response = $client->request('GET', 'oauth2/access_token', [
                'query' => [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'appid' => $_ENV['TEACHER_WEIXIN_APP_ID'],
                    'secret' => $_ENV['TEACHER_WEIXIN_APP_SECRET']
                ]
            ]
        );

        if (200 == $response->getStatusCode()) {
            $body = $response->getBody();
            $data = json_decode($body, 1);
            if (!empty($data)) {
                return $data;
            }
        }

        return false;
    }
}
