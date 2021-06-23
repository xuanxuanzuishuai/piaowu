<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/4/20
 * Time: 12:02
 */
namespace App\Models\Dss;

use App\Libs\Constants;
use App\Libs\UserCenter;

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
    const BUSI_TYPE_SHOW_MINAPP = 10; //学生测评分享小程序
    const BUSI_TYPE_AI_PLAY_MINAPP = 12;    //上音社合作-小叶子AI智能陪练小程序

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
     * @param null $appId
     * @param int $userType
     * @param int $busiType
     * @param array $param
     * @return mixed
     */
    public static function getByOpenId($openId, $appId = null, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER, $param = [])
    {
        $appId = self::dealAppId($appId);
        $status = $param['status'] ?? self::STATUS_NORMAL;
        $where = [
            'open_id'   => $openId,
            'status'    => $status,
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
     * @param array $param
     * @return mixed
     */
    public static function getByUserId($userId, $appId = Constants::SMART_APP_ID, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER, $param = [])
    {
        $status = $param['status'] ?? self::STATUS_NORMAL;
        $where = [
            'user_id'   => $userId,
            'status'    => $status,
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
     * @param array $param
     * @return array|null
     */
    public static function getByUuid($uuid, $appId = Constants::SMART_APP_ID, $userType = self::USER_TYPE_STUDENT, $busiType = self::BUSI_TYPE_STUDENT_SERVER, $param = [])
    {
        if (empty($uuid)) {
            return [];
        }
        $status = $param['status'] ?? self::STATUS_NORMAL;
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
            ':status'    => $status
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

    /**
     * @param $openid
     * @param string $appId
     * @param string $busi_type
     * @return array
     * 根据openId获取用户测评分享小程序绑定信息
     */
    public static function getUserInfoBindWX($openid, $appId = UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT, $busi_type = DssUserWeiXinModel::BUSI_TYPE_SHOW_MINAPP)
    {
        return self::dbRO()->select(
            DssStudentModel::$table . ' (s) ',
            [
                '[>]' . DssUserWeiXinModel::$table . ' (uw) ' => ['s.id' => 'user_id']
            ],
            [
                's.mobile',
                's.uuid',
                's.id'
            ],
            [
                'uw.open_id'   => $openid,
                'uw.user_type' => self::USER_TYPE_STUDENT,
                'uw.status'    => self::STATUS_NORMAL,
                'uw.busi_type' => $busi_type,
                'uw.app_id'    => $appId     // 默认测评分享小程序
            ]
        );
    }

    public static function getWxQr($openid, $userType, $status, $busiType)
    {
        return self::dbRO()->select(
            DssUserWeiXinModel::$table,
            [
                '[><]' . DssStudentModel::$table  => ['user_id' => 'id'],
                '[><]' . DssEmployeeModel::$table => [DssStudentModel::$table . '.assistant_id' => 'id']
            ],
            [
                DssEmployeeModel::$table . '.wx_qr',
                DssEmployeeModel::$table . '.wx_num',
                DssStudentModel::$table . '.uuid',
                DssStudentModel::$table . '.mobile'
            ],
            [
                DssUserWeiXinModel::$table . '.open_id'   => $openid,
                DssUserWeiXinModel::$table . '.user_type' => $userType,
                DssUserWeiXinModel::$table . '.status'    => $status,
                DssUserWeiXinModel::$table . '.busi_type' => $busiType,
            ]
        );
    }
}