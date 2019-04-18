<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/20
 * Time: 2:31 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class UserModel extends Model
{
    const SUB_STATUS_ON = 1;
    const SUB_STATUS_OFF = 0;

    public static $table = 'ai_user';
    public static $cacheKeyTokenPri = "token_";
    public static $cacheKeyUidPri = "uid_";
    public static $redisExpire = 2592000; // 30 days

    public static function getUserInfo($userID, $mobile)
    {
        if (empty($userID) && empty($mobile)) {
            return null;
        }

        $where = [];
        if (!empty($userID)) {
            $where[self::$table . '.id'] = $userID;
        }
        if (!empty($mobile)) {
            $where[self::$table . '.mobile'] = $mobile;
        }

        $db = MysqlDB::getDB();
        return $db->get(self::$table, [
            '[><]' . StudentModel::$table => ['student_id' => 'id']
        ], [
            self::$table . '.id',
            self::$table . '.student_id',
            self::$table . '.uuid',
            self::$table . '.mobile',
            self::$table . '.create_time',
            self::$table . '.status',
            self::$table . '.sub_status',
            self::$table . '.sub_start_date',
            self::$table . '.sub_end_date',
            StudentModel::$table . '.name',
            StudentModel::$table . '.thumb',
        ], $where);
    }

    public static function genUserToken($userID)
    {
        $rand = mt_rand(1, 9999);
        $token = md5(uniqid($userID . $rand, true));
        return $token;
    }

    /**
     * 缓存用户token，用于登录信息获取
     * @param $userID
     * @param $token
     * @return bool
     */
    public static function setUserToken($userID, $token)
    {
        $redis = RedisDB::getConn();

        self::delUserToken($userID);

        $tokenKey = self::$cacheKeyTokenPri . $token;
        $redis->setex($tokenKey, self::$redisExpire, $userID);

        $uidKey = self::$cacheKeyUidPri  . $userID;
        $redis->setex($uidKey, self::$redisExpire, $token);

        return true;
    }

    /**
     * 操作延长过期时间
     * @param $userID
     * @return bool
     */
    public static function refreshUserToken($userID)
    {
        $redis = RedisDB::getConn(self::$redisDB);

        $uidKey = self::$cacheKeyUidPri  . $userID;
        $ret = $redis->expire($uidKey, self::$redisExpire);
        if ($ret == 0) {
            return false;
        }

        $token = $redis->get($uidKey);
        if (empty($token)) {
            $redis->del($uidKey);
            return false;
        }

        $tokenKey = self::$cacheKeyTokenPri . $token;
        $ret = $redis->expire($tokenKey, self::$redisExpire);
        if ($ret == 0) {
            $redis->del($uidKey);
            return false;
        }

        $uid = $redis->get($tokenKey);
        if ($uid != $userID) {
            $redis->del([$uidKey, $tokenKey]);
            return false;
        }

        return true;
    }

    /**
     * 用uid获取token
     * @param $userID
     * @return string
     */
    public static function getUserToken($userID)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $uidKey = self::$cacheKeyUidPri  . $userID;
        return $redis->get($uidKey);
    }

    /**
     * 用token获取uid
     * @param $token
     * @return string
     */
    public static function getUserUid($token)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $tokenKey = self::$cacheKeyTokenPri . $token;
        return $redis->get($tokenKey);
    }

    /**
     * 删除用户token cache
     * @param $userID
     */
    public static function delUserToken($userID)
    {
        $redis = RedisDB::getConn(self::$redisDB);

        $uidKey = self::$cacheKeyUidPri  . $userID;
        $token = $redis->get($uidKey);
        $tokenKey = self::$cacheKeyTokenPri . $token;
        $redis->del([$uidKey, $tokenKey]);
    }
}