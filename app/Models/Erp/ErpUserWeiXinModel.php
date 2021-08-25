<?php

namespace App\Models\Erp;

use App\Libs\Constants;
use App\Libs\WeChat\WeChatMiniPro;

class ErpUserWeiXinModel extends ErpModel
{
    public static $table = 'erp_user_weixin';

    //用户类型
    const USER_TYPE_STUDENT = 1;

    //业务类型
    const BUSI_TYPE_STUDENT_SERVICE = 1;

    //业务线(不区分教师还是学生)
    const PANDA_USER_APP = '1';

    /**
     * 通过openid确认用户信息
     * @param string $openId
     * @return mixed
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getUserInfoByOpenId(string $openId)
    {
        $db = self::dbRO();

        $user = $db->get(
            self::$table,
            [
                "[><]" . ErpStudentAppModel::$table => ['user_id' => 'student_id'],
            ],
            [
                self::$table . '.user_id',
                ErpStudentAppModel::$table . '.status',
                ErpStudentAppModel::$table . '.create_time',
            ],
            [
                "AND" => [
                    self::$table . '.user_type'            => self::USER_TYPE_STUDENT,
                    self::$table . '.busi_type'            => self::BUSI_TYPE_STUDENT_SERVICE,
                    self::$table . '.open_id'              => $openId,
                    self::$table . '.app_id'               => self::PANDA_USER_APP,
                    ErpStudentAppModel::$table . '.app_id' => self::PANDA_USER_APP
                ],
            ]
        );
        $user['open_id'] = $openId;
        return $user;
    }
}