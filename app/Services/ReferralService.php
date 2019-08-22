<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/21
 * Time: 2:32 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\EmployeeModel;
use App\Models\GiftCodeModel;
use App\Models\ReferralModel;

class ReferralService
{
    /** 转介绍奖励服务时长 */
    const REWORDS_SUB_NUM = 7;
    const REWORDS_SUB_UNITS = GiftCodeModel::CODE_TIME_DAY;

    /**
     * 添加转介绍记录
     * @param int $referrerId 介绍人id
     * @param int $refereeId 被介绍人id
     * @param int $type 转介绍类型
     * @throws RunTimeException
     */
    public static function addReferral($referrerId, $refereeId, $type)
    {
        $id = ReferralModel::insertRecord([
            'referrer_id' => $referrerId,
            'referee_id' => $refereeId,
            'type' => $type,
            'create_time' => time(),
            'given_rewards' => Constants::STATUS_FALSE,
            'given_rewards_time' => null,
        ], false);

        if (empty($id)) {
            throw new RunTimeException(['insert_failure']);
        }
    }

    /**
     * 检查转介绍奖励
     * @param int $refereeId 被介绍人
     * @param int $type 转介绍类型
     * @return bool 是否发送奖励
     */
    public static function checkReferralRewards($refereeId, $type)
    {
        $referral = ReferralModel::getByRefereeId($refereeId, $type);
        if (empty($referral)) {
            return false;
        }

        // 发送奖励失败不影响正常流程, 发送错误记录到sentry
        try {
            if ($referral['given_rewards'] == Constants::STATUS_TRUE) {
                throw new RunTimeException(['referral_rewards_has_been_given']);
            }

            switch ($type) {
                case ReferralModel::REFERRAL_TYPE_WX_SHARE:
                    self::getRewordsSubDuration($referral['referrer_id']);
                    break;
                default:
                    throw new RunTimeException(['referral_type_is_invalid']);
            }
        } catch (RunTimeException $e) {
            $e->sendCaptureMessage([
                '$referral' => $referral,
            ]);

            return true;
        }

        return true;
    }

    /**
     * 发送奖励时长
     * @param $referrerId
     * @throws RunTimeException
     */
    public static function getRewordsSubDuration($referrerId)
    {
        GiftCodeService::createByStudent(
            self::REWORDS_SUB_NUM,
            self::REWORDS_SUB_UNITS,
            GiftCodeModel::BUYER_TYPE_REFERRAL,
            $referrerId,
            GiftCodeModel::CREATE_BY_SYSTEM,
            EmployeeModel::SYSTEM_EMPLOYEE_ID,
            true,
            'referral_gift',
            time()
        );
    }
}
