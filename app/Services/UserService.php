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
use App\Libs\Erp;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserWeiXinModel;
use App\Models\UserWeiXinModel;

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
        switch ($appId) {
            case Constants::SMART_APP_ID:
                return DssUserWeiXinModel::getRecord([
                    'user_id' => $userId,
                    'open_id' => $openId,
                    'user_type' => $userType,
                    'busi_type' => $busiType,
                    'status' => DssUserWeiXinModel::STATUS_NORMAL
                ]);
            case UserCenter::AUTH_APP_ID_OP_AGENT:
                return UserWeiXinModel::getRecord([
                    'user_id' => $userId,
                    'open_id' => $openId,
                    'user_type' => $userType,
                    'busi_type' => $busiType,
                    'status' => DssUserWeiXinModel::STATUS_NORMAL
                ]);
            default:
                break;
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
        } elseif ($appId == Constants::REAL_APP_ID) {
            return ErpUserWeiXinModel::getStudentWxInfo($userId);
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
     * @return mixed|null
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function studentRegisterBound($appId, $mobile, $channelId, $openId = NULL, $busiType = NULL, $userType = NULL, $refereeId = NULL)
    {
        if ($appId == Constants::SMART_APP_ID) {
           return (new Dss())->studentRegisterBound([
                'mobile' => (string)$mobile,
                'channel_id' => $channelId,
                'open_id' => $openId,
                'busi_type' => $busiType,
                'user_type' => $userType,
                'referee_id' => $refereeId
            ]);
        }
    }

    /**
     * 记录用户7天内活跃
     * @param $data
     * @return bool
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function recordUserActiveConsumer($data)
    {
        if (empty($data['user_id']) || StudentService::isAnonymousStudentId($data['user_id'])) {
            return false;
        }
        $userWx = DssUserWeiXinModel::getByUserId($data['user_id']);
        if (empty($userWx['open_id'])) {
            return false;
        }
        $date = date('Ymd');
        $redis = RedisDB::getConn();
        $expire = WechatService::KEY_WECHAT_DAILY_ACTIVE_EXPIRE; // 8 days
        $key = WechatService::KEY_WECHAT_DAILY_ACTIVE . $date;
        $redis->hset($key, $userWx['open_id'], time());
        $redis->expire($key, $expire);
        if ($data['update'] === true) {
            WechatService::updateUserTagByUserId($data['user_id']);
        }
        return true;
    }

    /**用户修改登录手机号 清除登录信息
     * @param $data
     */
    public static function userChangeLoginMobile($data)
    {
        $appIdArray = [Constants::SMART_APP_ID, Constants::REAL_APP_ID];
        if (empty(array_intersect($appIdArray, $data['auth_app_id']))) {
            SimpleLogger::info('not support deal', []);
            return;
        }

        $student = DssStudentModel::getRecord(['uuid' => $data['uuid']]);

        if (empty($student)) {
            SimpleLogger::info('not found student', []);
        }

        //清除微信登录信息
        WechatTokenService::delTokenByUserId($student['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, $appIdArray);

        //清除app登录信息
        AppTokenService::delUserTokenByUserId($student['id'], Constants::SMART_APP_ID);
    }

    /**
     * @param $studentId
     * @return bool
     * 判断用户是否是 智能付费有效用户
     */
    public static function judgeUserValidPay($studentId)
    {
        $canExchangeNum = (new Dss())->getUserCanExchangeNum(['student_id' => $studentId])['can_exchange_num'];
        if ($canExchangeNum <= 0) {
            SimpleLogger::info('not valid pay user', ['student_id' => $studentId, 'can_exchange_num' => $canExchangeNum]);
            return false;
        }
        return true;
    }

    /**
     * 获取学生身份属性
     * @param $appId
     * @param $studentId
     * @return array|mixed
     */
    public static function getStudentIdentityAttributeById($appId, $studentId)
    {
        $studentIdAttribute = [];
        if (empty($studentId)) {
            return [];
        }
        if ($appId == Constants::REAL_APP_ID) {
            $studentInfo = ErpStudentModel::getRecord(['id' => $studentId], ['uuid']);
            $studentIdAttribute = (new Erp())->getStudentIdentityAttribute($studentInfo['uuid'] ?? '');
            SimpleLogger::info('getStudentIdentityAttributeById', [$studentId, $studentInfo, $studentIdAttribute]);

        }
        return $studentIdAttribute;
    }

    /**
     * 检查真人用户是否是有效付费用户
     * @param $studentId
     * @param int $startFirstPayTime
     * @param int $endFirstPayTime
     * @return bool
     */
    public static function checkRealStudentIdentityIsNormal($studentId, int $startFirstPayTime = 0, int $endFirstPayTime = 0): bool
    {
        $studentIdAttribute = self::getStudentIdentityAttributeById(Constants::REAL_APP_ID, $studentId);
        if (empty($studentIdAttribute)) {
            return false;
        }
        // 未付费
        if (!isset($studentIdAttribute['is_real_person_paid']) || $studentIdAttribute['is_real_person_paid'] != Erp::USER_IS_PAY_YES) {
            return false;
        }
        // 没有剩余付费课程数
        if (!isset($studentIdAttribute['paid_course_remainder_num']) || $studentIdAttribute['paid_course_remainder_num'] <= 0) {
            return false;
        }
        // 用户付费起始时间不为0 ，用户付费时间大于起始时间
        if (!($startFirstPayTime > 0 && isset($studentIdAttribute['first_pay_time']) && $studentIdAttribute['first_pay_time'] >= $startFirstPayTime)) {
            return false;
        }
        // 用户付费截止时间不为0 ，用户付费小于截止时间
        if (!($endFirstPayTime > 0 && isset($studentIdAttribute['first_pay_time']) && $studentIdAttribute['first_pay_time'] < $endFirstPayTime)) {
            return false;
        }
        return true;
    }
}
