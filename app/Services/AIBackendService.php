<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/6/11
 * Time: 12:24 PM
 */

namespace App\Services;


use App\Libs\RedisDB;
use App\Models\StudentModelForApp;

class AIBackendService
{
    const TOKEN_PRI = 'AI';
    const TOKEN_EXPIRE = 3600;

    /**
     * 生成用于访问AIBackend服务的token
     * 格式为 AI_abc123
     * @param $studentId
     * @return string
     */
    public static function genStudentToken($studentId)
    {
        $token = self::TOKEN_PRI . '_' . StudentModelForApp::genStudentToken($studentId);
        $redis = RedisDB::getConn();
        $redis->setex($token, self::TOKEN_EXPIRE, $studentId);
        return $token;
    }

    /**
     * 根据token获取studentId
     * @param $token
     * @return string
     */
    public static function validateStudentToken($token)
    {
        $redis = RedisDB::getConn();
        $studentId = $redis->get($token);
        return $studentId;
    }
}