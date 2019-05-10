<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:40 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\RedisDB;

class OrganizationModelForApp extends Model
{
    public static $table = "organization";
    public static $redisPri = "organization";

    public static $orgTokenPri = "org_token_";
    public static $orgTokenExpire = 2592000; // 30 days

    public static $orgTeacherTokenPri = "org_teacher_token_";
    public static $orgTeacherTokenExpire = 14400; // 4h

    public static $orgOnlinePri = "org_online_";


    /**
     * 保存机构登录状态的token的key
     * org_token_abc123
     * 内容为机构id
     * @param $token
     * @return string
     */
    public static function getOrgTokenKey($token)
    {
        return self::$orgTokenPri . $token;
    }

    /**
     * 保存老师登录状态的token的key
     * org_teacher_token_1_abc123
     * 内容为上课老师数据的JSON {"teacher_id":1,"student_id":2,"org_id":3,"token":"abc123"}
     * @param $orgId
     * @param $token
     * @return string
     */
    public static function getOrgTeacherTokenKey($orgId, $token)
    {
        return self::$orgTeacherTokenPri . $orgId . '_' . $token;
    }

    /**
     * 保存机构上课老师token的key
     * org_online_1
     * redis数据格式为list item内容为上课老师数据的JSON 按从新到旧排序
     * @param $orgId
     * @return string
     */
    public static function getOrgOnlineTeacherKey($orgId)
    {
        return self::$orgOnlinePri . $orgId;
    }


    public static function genToken($id)
    {
        $rand = mt_rand(1, 9999);
        $token = md5(uniqid($id . $rand, true));
        return $token;
    }

    /**
     * 缓存用户token，用于登录信息获取
     * @param $orgId
     * @param $account
     * @param $token
     * @return bool
     */
    public static function setOrgToken($orgId, $account, $token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTokenKey($token);
        $data = ['org_id' => $orgId, 'account' => $account];
        $value = json_encode($data);
        $redis->setex($tokenKey, self::$orgTokenExpire, $value);

        return true;
    }

    /**
     * 获取机构token对应org_id
     * @param $token
     * @return array ['org_id' => 1, 'account' => '12345678']
     */
    public static function getOrgCacheByToken($token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTokenKey($token);
        $value = $redis->get($tokenKey);
        $data = json_decode($value, true);

        return $data;
    }

    /**
     * 操作延长过期时间
     * @param $token
     * @return bool
     */
    public static function refreshOrgToken($token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTokenKey($token);
        $ret = $redis->expire($tokenKey, self::$orgTokenExpire);
        if ($ret == 0) {
            return false;
        }

        return true;
    }

    public static function setOrgTeacherToken($data, $orgId, $token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTeacherTokenKey($orgId, $token);
        $value = json_encode($data);
        $redis->setex($tokenKey, self::$orgTeacherTokenExpire, $value);

        return true;
    }

    public static function getOrgTeacherCacheByToken($orgId, $token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTeacherTokenKey($orgId, $token);
        $value = $redis->get($tokenKey);
        $data = json_decode($value, true);

        return $data;
    }

    public static function refreshOrgTeacherToken($orgId, $token)
    {
        $redis = RedisDB::getConn();
        $tokenKey = self::getOrgTeacherTokenKey($orgId, $token);
        $ret = $redis->expire($tokenKey, self::$orgTeacherTokenExpire);
        if ($ret == 0) {
            return false;
        }

        return true;
    }

    public static function delOrgTeacherTokens($orgId, $tokens)
    {
        $redis = RedisDB::getConn();
        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }
        $tokenKeys = [];
        foreach ($tokens as $token) {
            $tokenKeys[] = self::getOrgTeacherTokenKey($orgId, $token);
        }
        $redis->del($tokenKeys);
    }

    public static function setOnlineTeacher($data, $orgId)
    {
        $redis = RedisDB::getConn();
        $key = self::getOrgOnlineTeacherKey($orgId);
        $value = json_encode($data);
        $redis->set($key, $value);

        return true;
    }

    public static function getOnlineTeacher($orgId)
    {
        $redis = RedisDB::getConn();
        $key = self::getOrgOnlineTeacherKey($orgId);
        $value = $redis->get($key);
        $data = json_decode($value, true);

        return $data ?? [];
    }

    public static function getOrgStudentsByTeacherId($orgId, $teacherId)
    {
        $db = MysqlDB::getDB();
        return $db->select(TeacherStudentModel::$table . '(ts)',
            [
                '[><]' . StudentModel::$table . '(s)' => ['ts.student_id' => 'id']
            ],
            ['s.id', 's.name'],
            [
                'ts.org_id' => $orgId,
                'ts.teacher_id' => $teacherId,
                'ts.status' => TeacherStudentModel::STATUS_NORMAL,
                's.status' => StudentModel::STATUS_NORMAL
            ]);
    }
}