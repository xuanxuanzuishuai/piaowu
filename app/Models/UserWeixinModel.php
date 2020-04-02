<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models;

use App\Libs\MysqlDB;


class UserWeixinModel extends Model
{
    public static $table = "user_weixin";

    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;

    const USER_TYPE_STUDENT = 1; // 学生
    const USER_TYPE_TEACHER = 2;  // 老师 (废弃)
    const USER_TYPE_STUDENT_ORG = 3; // 学生机构号 (废弃)

    const BUSI_TYPE_STUDENT_SERVER = 1; // 学生服务号
    const BUSI_TYPE_TEACHER_SERVER = 2; // 老师服务号 (废弃)
    const BUSI_TYPE_EXAM_MINAPP = 6; // 音基小程序
    const BUSI_TYPE_STUDENT_MINAPP = 7; // 学生app推广小程序

    /** 获取最近绑定的该open_id的信息
     * @param $openId
     * @param $appId
     * @param $userType
     * @param $busiType
     * @return array
     */
    public static function getBoundInfoByOpenId($openId, $appId, $userType, $busiType)
    {
        $where = [
            'open_id' => $openId,
            'status' => self::STATUS_NORMAL,
            'app_id' => $appId,
            'user_type' => $userType,
            'busi_type' => $busiType,
            "ORDER" => ["id" => "DESC"]
        ];

        return self::getRecord($where, false);
    }

    public static function getBoundInfoByUserId($userId, $appId, $userType, $busiType)
    {
        $where = [
            'user_id' => $userId,
            'status' => self::STATUS_NORMAL,
            'app_id' => $appId,
            'user_type' => $userType,
            'busi_type' => $busiType,
            "ORDER" => ["id" => "DESC"]
        ];

        return self::getRecord($where, false);
    }

    /**
     * 绑定用户 并清除历史绑定关系
     * @param $openId
     * @param $userId
     * @param $appId
     * @param $userType
     * @param $busiType
     * @return int|null
     */
    public static function boundUser($openId, $userId, $appId, $userType, $busiType)
    {
        self::batchUpdateRecord(['status' => self::STATUS_DISABLE], [
            'status' => self::STATUS_NORMAL,
            'OR' => [
                'open_id' => $openId,
                'AND' => [
                    "user_id" => $userId,
                    "app_id" => $appId,
                    "user_type" => $userType,
                    "busi_type" => $busiType,
                ]
            ]
        ], false);

        return self::insertRecord([
            "open_id" => $openId,
            "user_id" => $userId,
            "app_id" => $appId,
            "user_type" => $userType,
            "busi_type" => $busiType,
            "status" => self::STATUS_NORMAL,
        ], false);
    }

    /**
     * 解绑学生微信
     * @param $openId
     * @param $userId
     * @param $appId
     * @param $userType
     * @param $busiType
     */
    public static function unboundUser($openId, $userId, $appId, $userType, $busiType)
    {
        self::batchUpdateRecord(['status' => self::STATUS_DISABLE], [
            'status' => self::STATUS_NORMAL,
            'OR' => [
                'open_id' => $openId,
                'AND' => [
                    "user_id" => $userId,
                    "app_id" => $appId,
                    "user_type" => $userType,
                    "busi_type" => $busiType,
                ]
            ]
        ], false);
    }
}