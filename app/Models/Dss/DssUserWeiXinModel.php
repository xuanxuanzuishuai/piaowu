<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

use App\Libs\Constants;

class DssUserWeiXinModel extends DssModel
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
    const BUSI_TYPE_REFERRAL_MINAPP = 8; // 转介绍小程序

    /**
     * 处理App ID 默认值
     * @param null $appId
     * @return int|mixed
     */
    public static function dealAppId($appId = null)
    {
        if (!empty($appId)) {
            return $appId;
        }
        return Constants::SMART_APP_ID;
    }
    /**
     * 根据openid查询
     * @param $openId
     * @param int $appId
     * @param int $userType
     * @param int $busiType
     * @return mixed
     */
    public static function getByOpenId($openId, $appId = null, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER)
    {
        $appId = self::dealAppId($appId);
        $where = [
            'open_id'   => $openId,
            'status'    => self::STATUS_NORMAL,
            'app_id'    => $appId,
            'user_type' => $userType,
            'busi_type' => $busiType,
            "ORDER"     => ["id" => "DESC"]
        ];
        return self::getRecord($where);
    }

    /**
     * 根据user_id查询
     * @param $userId
     * @param int $appId
     * @param int $userType
     * @param int $busiType
     * @return mixed
     */
    public static function getByUserId($userId, $appId = Constants::SMART_APP_ID, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER)
    {
        $where = [
            'user_id'   => $userId,
            'status'    => self::STATUS_NORMAL,
            'app_id'    => $appId,
            'user_type' => $userType,
            'busi_type' => $busiType,
            "ORDER"     => ["id" => "DESC"]
        ];
        return self::getRecord($where);
    }

    /**
     * 根据UUID查询
     * @param $uuid
     * @param int $appId
     * @param int $userType
     * @param int $busiType
     * @return array|null
     */
    public static function getByUuid($uuid, $appId = Constants::SMART_APP_ID, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER)
    {
        $db = self::dbRO();
        $sql = "
           SELECT
               u.user_id,
               u.open_id
           FROM " . self::getTableNameWithDb() . " AS u
           INNER JOIN " . DssStudentModel::getTableNameWithDb() . " as s on s.id = u.user_id
           where s.uuid in ('" . implode("','", $uuid) . "')
           and u.app_id    = :app_id
           and u.user_type = :user_type
           and u.busi_type = :busi_type
           and u.status    = :status ";

        $map = [
            ':app_id'    => $appId,
            ':user_type' => $userType,
            ':busi_type' => $busiType,
            ':status'    => self::STATUS_NORMAL
        ];
        return $db->queryAll($sql, $map);
    }


    /**
     * 只获取学生服务号， 转介绍小程序 的学生id
     * @param $userId
     * @param $fields
     * @return mixed
     */
    public static function getUserWeiXinListByUserid($userId)
    {
        $db = self::dbRO();
        $dssStudentTable = DssStudentModel::getTableNameWithDb();
        $dssUserWeiXinTable = DssUserWeiXinModel::getTableNameWithDb();
        $sql = 'SELECT 
        d_s.id as user_id,
        d_u.open_id,
        d_u.app_id,
        d_u.busi_type,
        d_u.user_type
        FROM '.$dssStudentTable.' as d_s '.
        ' LEFT JOIN '.$dssUserWeiXinTable.' as d_u ON d_u.user_id = d_s.id'.
        ' AND d_u.app_id='.Constants::SMART_APP_ID.
        ' AND d_u.busi_type='.self::BUSI_TYPE_STUDENT_SERVER.
        ' AND d_u.user_type='.self::USER_TYPE_STUDENT.
        ' AND d_u.status='.self::STATUS_NORMAL .
        ' WHERE d_s.id in ('.implode(',', $userId).')';
        $list = $db->queryAll($sql);
        return $list;
    }
}