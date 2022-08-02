<?php

namespace App\Models\Erp;

use App\Libs\Constants;

class ErpUserWeiXinModel extends ErpModel
{
    public static $table = 'erp_user_weixin';
    
    const STATUS_NORMAL = 1;
    const STATUS_DISABLE = 2;
    
    //用户类型
    const USER_TYPE_STUDENT = 1;

    //业务类型
    const BUSI_TYPE_STUDENT_SERVER = 1;

    //业务线(不区分教师还是学生)
    const PANDA_USER_APP = '1';

    /**
     * 通过openid确认用户信息
     * @param string $openId
     * @return mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getUserInfoByOpenId(string $openId, $businessType = null)
    {
        $db = self::dbRO();
        $businessType = $businessType ?? self::BUSI_TYPE_STUDENT_SERVER;
        $user = $db->get(
            self::$table,
            [
                "[><]" . ErpStudentAppModel::$table => ['user_id' => 'student_id'],
                "[><]" . ErpStudentModel::$table => ['user_id' => 'id'],
            ],
            [
                self::$table . '.user_id',
                ErpStudentAppModel::$table . '.status',
                ErpStudentAppModel::$table . '.create_time',
                ErpStudentModel::$table . '.mobile',
            ],
            [
                "AND" => [
                    self::$table . '.user_type'            => self::USER_TYPE_STUDENT,
                    self::$table . '.busi_type'            => $businessType,
                    self::$table . '.open_id'              => $openId,
                    self::$table . '.app_id'               => self::PANDA_USER_APP,
                    self::$table . '.status'               => self::STATUS_NORMAL,
                    ErpStudentAppModel::$table . '.app_id' => self::PANDA_USER_APP
                ],
            ]
        );
        $user['open_id'] = $openId;
        return $user;
    }

    /**
     * 真人 - 根据学生id获取用户当前绑定的微信
     * @param $userId
     * @return array|mixed
     */
    public static function getStudentWxInfo($userId)
    {
        $userWxInfo = self::getRecord([
            'user_id'   => $userId,
            'app_id'    => Constants::REAL_APP_ID,
            'user_type' => self::PANDA_USER_APP,
            'status'    => self::STATUS_NORMAL,
            'busi_type' => self::BUSI_TYPE_STUDENT_SERVER,
            'ORDER'     => ['id' => 'DESC'],
        ]);
        return is_array($userWxInfo) ? $userWxInfo : [];
    }
}
