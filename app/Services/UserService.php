<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/8/20
 * Time: 下午2:52
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Dss;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;

/**
 * 公共调用
 * Class UserService
 * @package App\Services
 */
class UserService
{
    /**
     * 绑定微信的信息
     * @param $appId
     * @param $openId
     * @param $userType
     * @param $busiType
     * @return mixed|null
     */
    public static function getUserWeiXinInfo($appId, $openId, $userType, $busiType)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return DssUserWeiXinModel::getRecord([
                'open_id' => $openId,
                'user_type' => $userType,
                'busi_type' => $busiType,
                'status' => DssUserWeiXinModel::STATUS_NORMAL
            ]);
        }
        return NULL;
    }

    /**
     * 用户和当前openId是否还有绑定关系
     * @param $appId
     * @param $userId
     * @param $openId
     * @param $userType
     * @param $busiType
     * @return mixed|null
     */
    public static function getUserWeiXinInfoAndUserId($appId, $userId, $openId, $userType, $busiType)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return DssUserWeiXinModel::getRecord([
                'user_id' => $userId,
                'open_id' => $openId,
                'user_type' => $userType,
                'busi_type' => $busiType,
                'status' => DssUserWeiXinModel::STATUS_NORMAL
            ]);
        }
        return NULL;
    }

    /**
     * 用户id查找用户微信信息
     * @param $appId
     * @param $userId
     * @param $userType
     * @param $busiType
     * @return mixed|null
     */
    public static function getUserWeiXinInfoByUserId($appId, $userId, $userType, $busiType)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return DssUserWeiXinModel::getRecord([
                'user_id' => $userId,
                'user_type' => $userType,
                'busi_type' => $busiType,
                'status' => DssUserWeiXinModel::STATUS_NORMAL
            ]);
        }
        return NULL;
    }

    /**
     * 手机号获取用户信息
     * @param $appId
     * @param $mobile
     * @return mixed
     */
    public static function getUserInfo($appId, $mobile)
    {
        if ($appId == Constants::SMART_APP_ID) {
            return DssStudentModel::getRecord(['mobile' => $mobile]);
        }
    }

    /**
     * 学生注册
     * @param $appId
     * @param $mobile
     * @param $channelId
     * @param null $openId
     * @param null $busiType
     * @param null $userType
     * @param null $refereeId
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function studentRegisterBound($appId, $mobile, $channelId, $openId = NULL, $busiType = NULL, $userType = NULL, $refereeId = NULL)
    {
        if ($appId == Constants::SMART_APP_ID) {
            (new Dss())->studentRegisterBound([
                'mobile' => $mobile,
                'channel_id' => $channelId,
                'open_id' => $openId,
                'busi_type' => $busiType,
                'user_type' => $userType,
                'referee_id' => $refereeId
            ]);
        }
    }

}