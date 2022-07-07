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
use App\Libs\RealDictConstants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\Erp\ErpUserWeiXinModel;
use App\Models\RealSharePosterDesignateUuidModel;
use App\Models\UserWeiXinModel;
use App\Services\TraitService\TraitDssUserService;

/**
 * 公共调用
 * Class UserService
 * @package App\Services
 */
class UserService
{
    use TraitDssUserService;

    private static $studentAttribute = [];

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
     * @param string $countryCode
     * @return mixed|null
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function studentRegisterBound($appId, $mobile, $channelId, $openId = NULL, $busiType = NULL, $userType = NULL, $refereeId = NULL, $countryCode = '')
    {
        if ($appId == Constants::SMART_APP_ID) {
            return (new Dss())->studentRegisterBound([
                'mobile'       => (string)$mobile,
                'country_code' => (string)$countryCode,
                'channel_id'   => $channelId,
                'open_id'      => $openId,
                'busi_type'    => $busiType,
                'user_type'    => $userType,
                'referee_id'   => $refereeId
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

    /**用户修改登录手机号 清除登录信息
     * @param $data
     */
    public static function userChangeMobile($data)
    {
        $appIdArray = [Constants::SMART_APP_ID, Constants::REAL_APP_ID];
        foreach ($data as $value) {
            $student = DssStudentModel::getRecord(['uuid' => $value['uuid']]);
            if (empty($student)) {
                SimpleLogger::info('not found student', []);
                continue;
            }
            //清除微信登录信息
            WechatTokenService::delTokenByUserId($student['id'], DssUserWeiXinModel::USER_TYPE_STUDENT, $appIdArray);

            //清除app登录信息
            AppTokenService::delUserTokenByUserId($student['id'], Constants::SMART_APP_ID);
        }
    }

    /**
     * @param $studentId
     * @return bool
     * 判断用户是否是 智能付费有效用户
     */
    public static function judgeUserValidPay($studentId)
    {
        $canExchangeNum = (new Dss())->getUserCanExchangeNum(['student_id' => $studentId]);
        SimpleLogger::info('valid pay user', ['student_id' => $studentId, 'can_exchange_num' => $canExchangeNum]);
        // 2022.06.27 改为有剩余课时即可，参与月月有奖以及可以得到转介绍奖励（如果后端勾选的推荐人身份包含年卡未过期）
        if ($canExchangeNum['has_review_course'] != DssStudentModel::REVIEW_COURSE_1980) {
            return false;
        }
        if (empty($canExchangeNum['sub_end_date']) || $canExchangeNum['sub_end_date'] < date("Ymd")) {
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
    public static function getStudentIdentityAttributeById($appId, $studentId, $studentUUID = '')
    {
        $studentIdAttribute = [];
        if (empty($studentId) && empty($studentUUID)) {
            return [];
        }
        if ($appId == Constants::REAL_APP_ID) {
            if (empty($studentUUID)) {
                $studentInfo = ErpStudentModel::getRecord(['id' => $studentId], ['uuid']);
                $studentUUID = $studentInfo['uuid'] ?? '';
            }
        }
        $key = 'student_identity_attribute_'.$appId . '-' . $studentUUID;
        if (!isset(self::$studentAttribute[$key])) {
            if ($appId == Constants::REAL_APP_ID) {
                self::$studentAttribute[$key] = (new Erp())->getStudentIdentityAttribute($studentUUID);
                SimpleLogger::info('getStudentIdentityAttributeById', [$studentId, $studentInfo ?? [], $studentIdAttribute]);
            }
        }
        SimpleLogger::info('getStudentIdentityAttributeById', [$studentId, $studentInfo ?? [], $studentIdAttribute, self::$studentAttribute[$key], $key]);
        return self::$studentAttribute[$key];
    }

    /**
     * 检查真人用户是否是有效付费用户
     * @param $studentId
     * @param array $studentIdAttribute
     * @return bool
     */
    public static function checkRealStudentIdentityIsNormal($studentId, array $studentIdAttribute = []): bool
    {
        if (empty($studentIdAttribute)) {
            $studentIdAttribute = self::getStudentIdentityAttributeById(Constants::REAL_APP_ID, $studentId);
            if (empty($studentIdAttribute)) {
                return false;
            }
        }
        // 未付费
        if (!isset($studentIdAttribute['is_real_person_paid']) || $studentIdAttribute['is_real_person_paid'] != Erp::USER_IS_PAY_YES) {
            return false;
        }
        // 没有剩余付费课程数
        if (!isset($studentIdAttribute['paid_course_remainder_num']) || $studentIdAttribute['paid_course_remainder_num'] <= 0) {
            return false;
        }
        // 没有付费时间
        if (empty($studentIdAttribute['first_pay_time'])) {
            return false;
        }
        return true;
    }

    /**
     * 检查uuid是否存在
     * @param $appId
     * @param $studentUuid
     * @param $activityId
     * @return array
     */
    public static function checkStudentUuidExists($appId, $studentUuid, $activityId = 0): array
    {
        $returnData = [
            'no_exists_uuid' => [],
            'activity_having_uuid' => [],
        ];
        if (empty($studentUuid)) {
            return $returnData;
        }
        if (!in_array($appId, [Constants::REAL_APP_ID])) {
            return $returnData;
        }
        $uuidChunkList = array_chunk($studentUuid, 900);
        foreach ($uuidChunkList as $_uuids) {
            $studentList = [];
            if ($appId == Constants::REAL_APP_ID) {
                $studentList = ErpStudentModel::getStudentInfoByUuids($_uuids);
            }
            // 不存在 - 取不同
            $returnData['no_exists_uuid'] = array_merge($returnData['no_exists_uuid'], array_values(array_diff($_uuids, array_column($studentList, 'uuid'))));

            // 如果指定了活动，取活动中已经存在的UUID
            if (!empty($activityId)) {
                $activityUUIDList = [];
                if ($appId == Constants::REAL_APP_ID) {
                    $activityUUIDList = RealSharePosterDesignateUuidModel::getRecords(['activity_id' => $activityId, 'uuid' => $_uuids], ['uuid']);
                    $activityUUIDList = array_column($activityUUIDList, 'uuid');
                }
                // 存在 - 读到的都是存在的
                $returnData['activity_having_uuid'] = array_merge($returnData['activity_having_uuid'], $activityUUIDList);
            }
        }
        unset($_uuids);

        // 去重
        $returnData['activity_having_uuid'] = array_unique($returnData['activity_having_uuid']);
        $returnData['no_exists_uuid'] = array_unique($returnData['no_exists_uuid']);
        return $returnData;
    }
}
