<?php
/**
 * Created by PhpStorm.
 * Student: fll
 * Date: 2018/11/6
 * Time: 8:09 PM
 */

namespace App\Models;

use App\Libs\Constants;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;

/**
 *
 *
 * Class StudentAppModel
 * @package App\Models
 *
 */
class StudentModelForApp extends Model
{
    const SUB_STATUS_ON = 1;
    const SUB_STATUS_OFF = 0;

    public static $table = 'student';
    public static $redisPri = "student";
    public static $cacheKeyTokenPri = "token_";
    public static $cacheKeyUidPri = "uid_";
    public static $cacheKeyAnonymousPri = 'anonymous_';
    public static $redisExpire = 2592000; // 30 days

    /**
     * 生成匿名用户id: 小于0的随机数字
     * @return int
     */
    public static function genAnonymousStudentId()
    {
        return rand(-1, PHP_INT_MIN);
    }

    public static function isAnonymousStudentId($id)
    {
        return $id < 0;
    }

    public static function getAnonymousStudentInfo($anonymousId)
    {
        return [
            'id' => $anonymousId,
            'uuid' => $anonymousId,
            'mobile' => '0',
            'create_time' => time(),
            'channel_id' => '0',
            'status' => '1',
            'sub_status' => '1',
            'sub_start_date' => '0',
            'sub_end_date' => '0',
            'trial_start_date' => '0',
            'trial_end_date' => '0',
            'act_sub_info' => '0',
            'first_pay_time' => '0',
            'has_review_course' => '0',
            'name' => '游客',
            'thumb' => '',
            'flags' => '0',
            'last_play_time' => '0',
            'collection_id' => '0',
            'student_is_set_pwd' => Constants::STATUS_TRUE,
        ];
    }

    public static function getStudentInfo($studentID, $mobile, $uuid = null)
    {
        if (empty($studentID) && empty($mobile) && empty($uuid)) {
            return null;
        }

        $where = [];
        if (!empty($studentID)) {
            $where[self::$table . '.id'] = $studentID;
        }
        if (!empty($mobile)) {
            $where[self::$table . '.mobile'] = $mobile;
        }
        if (!empty($uuid)) {
            $where[self::$table . '.uuid'] = $uuid;
        }

        $db = MysqlDB::getDB();
        return $db->get(self::$table, [
            self::$table . '.id',
            self::$table . '.uuid',
            self::$table . '.mobile',
            self::$table . '.country_code',
            self::$table . '.create_time',
            self::$table . '.channel_id',
            self::$table . '.status',
            self::$table . '.sub_status',
            self::$table . '.sub_start_date',
            self::$table . '.sub_end_date',
            self::$table . '.trial_start_date',
            self::$table . '.trial_end_date',
            self::$table . '.act_sub_info',
            self::$table . '.first_pay_time',
            self::$table . '.name',
            self::$table . '.thumb',
            self::$table . '.flags',
            self::$table . '.last_play_time',
            self::$table . '.has_review_course',
            self::$table . '.collection_id',
            self::$table . '.password',
        ], $where);
    }

    public static function getTeacherIds($studentID)
    {
        if (empty($studentID)) {
            return null;
        }

        $db = MysqlDB::getDB();
        $teacherIds = $db->select(TeacherStudentModel::$table, 'teacher_id', [
            'student_id' => $studentID,
            'status' => 1
        ]);
        return $teacherIds;
    }

    public static function genStudentToken($studentID)
    {
        $rand = mt_rand(1, 9999);
        $token = md5(uniqid($studentID . $rand, true));
        return $token;
    }

    public static function isAnonymousStudentToken($token)
    {
        $len = strlen(self::$cacheKeyAnonymousPri);
        return strncmp(self::$cacheKeyAnonymousPri, $token, $len) === 0;
    }

    public static function genAnonymousStudentToken($anonymousID)
    {
        return self::$cacheKeyAnonymousPri . self::genStudentToken($anonymousID);
    }

    /**
     * 缓存用户token，用于登录信息获取
     * @param $studentID
     * @param $token
     * @return bool
     */
    public static function setStudentToken($studentID, $token)
    {
        $redis = RedisDB::getConn();

        self::delStudentToken($studentID);

        $tokenKey = self::$cacheKeyTokenPri . $token;
        $redis->setex($tokenKey, self::$redisExpire, $studentID);

        $uidKey = self::$cacheKeyUidPri  . $studentID;
        $redis->setex($uidKey, self::$redisExpire, $token);

        return true;
    }

    /**
     * 操作延长过期时间
     * @param $studentID
     * @return bool
     */
    public static function refreshStudentToken($studentID)
    {
        $redis = RedisDB::getConn(self::$redisDB);

        $uidKey = self::$cacheKeyUidPri  . $studentID;
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
        if ($uid != $studentID) {
            $redis->del([$uidKey, $tokenKey]);
            return false;
        }

        return true;
    }

    /**
     * 用uid获取token
     * @param $studentID
     * @return string
     */
    public static function getStudentToken($studentID)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $uidKey = self::$cacheKeyUidPri  . $studentID;
        return $redis->get($uidKey);
    }

    /**
     * 用token获取uid
     * @param $token
     * @return string
     */
    public static function getStudentUid($token)
    {
        $redis = RedisDB::getConn(self::$redisDB);
        $tokenKey = self::$cacheKeyTokenPri . $token;
        return $redis->get($tokenKey);
    }

    /**
     * 删除用户token cache
     * @param $studentID
     */
    public static function delStudentToken($studentID)
    {
        $redis = RedisDB::getConn(self::$redisDB);

        $uidKey = self::$cacheKeyUidPri  . $studentID;
        $token = $redis->get($uidKey);
        $tokenKey = self::$cacheKeyTokenPri . $token;
        $redis->del([$uidKey, $tokenKey]);
    }
}