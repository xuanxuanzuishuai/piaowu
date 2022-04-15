<?php
/**
 * Created by PhpStorm.
 * User: liuguokun
 * Date: 2022/4/12
 * Time: 3:10 下午
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\Erp;
use App\Libs\NewSMS;
use App\Libs\RedisDB;
use App\Models\Erp\ErpStudentModel;

class StudentWebCommonService
{
    const cacheKeyTokenPri = "op_web_token_info_";
    const cacheUserTokenKeyPri = 'op_web_token_';
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
     * @param $uuid
     * @param $app_id
     * @return string
     */
    public static function generateToken($uuid, $app_id) {
        //当前用户是否已有已经在有效期的token
        $redis = RedisDB::getConn();
        $userKey = self::getUserTokenKey($uuid, $app_id);
        $hasExistToken = $redis->get($userKey);
        if (!empty($hasExistToken)) {
            return $hasExistToken;
        }

        $token = bin2hex(openssl_random_pseudo_bytes(16));
        $redis->setex($userKey, self::$redisExpire, $token);
        $key = self::getTokenKey($token);

        $redis->setex($key, self::$redisExpire, json_encode([
            "uuid" => $uuid,
            "app_id" => $app_id,
        ]));
        return $token;
    }

    /**
     * 系统已经存在的当前用户的token
     * @param $uuid
     * @param $appId
     * @return string
     */
    public static function getUserTokenKey($uuid, $appId)
    {
        return self::cacheUserTokenKeyPri . $appId . '_' . $uuid;
    }

    /**
     * 刷新用户token key过期时间
     * @param $uuid
     * @param $appId
     */
    public static function refreshUserToken($uuid, $appId)
    {
        $userKey = self::getUserTokenKey($uuid, $appId);
        $redis = RedisDB::getConn();
        $redis->expire($userKey, self::$redisExpire);
    }

    /**
     * 清除token有效
     * @param $uuid
     * @param $appId
     */
    public static function delUserTokenByUserId($uuid, $appId)
    {
        $userKey = self::getUserTokenKey($uuid, $appId);
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


    /**
     * 用户登录
     * @param $params
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function login($params)
    {
        $studentInfo = ErpStudentModel::getAppStudentInfo($params['mobile'], $params['app_id']);
        if (!empty($studentInfo)) {
            //生成token，返回用户信息
            $token = self::generateToken($params['uuid'], $params['app_id']);
            return [
                'app_id'     => $params['app_id'],
                'student_id' => $studentInfo['student_id'],
                'uuid'       => $studentInfo['uuid'],
                'mobile'     => $params['mobile'],
                'token'      => $token,
            ];
        }

        //注册用户信息
        if ($params['app_id'] == Constants::REAL_APP_ID) {
            //注册真人用户信息
            $studentInfo = (new Erp())->refereeStudentRegister([
                'app_id'       => $params['app_id'],
                'mobile'       => $params['mobile'],
                'country_code' => NewSMS::DEFAULT_COUNTRY_CODE,
                'channel_id'   => $params['channel_id'],
            ]);
        } elseif ($params['app_id'] == Constants::SMART_APP_ID) {
            //注册智能用户信息
            $studentInfo = (new Dss())->studentRegisterBound([
                'mobile'     => (string)$params['mobile'],
                'channel_id' => $params['channel_id']
            ]);
        }
        $token = self::generateToken($params['uuid'], $params['app_id']);
        return [
            'app_id'     => $params['app_id'],
            'student_id' => $studentInfo['student_id'],
            'uuid'       => $studentInfo['uuid'],
            'mobile'     => $params['mobile'],
            'token'      => $token,
        ];
    }
}