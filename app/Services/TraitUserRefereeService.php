<?php
/**
 * 用户推荐奖励
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\ReferralRulesModel;
use function AlibabaCloud\Client\json;

trait TraitUserRefereeService
{
    /**
     * 生成学生转介绍学生推荐奖励方法统一入口
     * 注意调用此方法前需要判断是否有转介绍关系
     * @param $appId
     * @param $packageType
     * @param $refereeInfo
     * @param $parentBillId
     * @param $studentInfo
     * @return bool
     * @throws RunTimeException
     */
    public static function createStudentRefereeAward($packageType, $refereeInfo, $parentBillId, $studentInfo)
    {
        // 没有推荐人
        if (empty($refereeInfo)) {
            SimpleLogger::info('not referee user', [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
            throw new RunTimeException(['not_referee_user'], [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
        }
        // 获取订单的时长
        $billInfo = DssGiftCodeModel::getRecord(['parent_bill_id' => $parentBillId], ['id', 'valid_num', 'valid_units']);
        if (empty($billInfo)) {
            SimpleLogger::info("parent_bill_id_is_not_found", [$parentBillId, $packageType, $refereeInfo, $billInfo]);
            throw new RunTimeException(['parent_bill_id_is_not_found'], [$packageType, $refereeInfo, $parentBillId, $studentInfo]);
        }

        // 获取邀请人身份
        $refereeIdentity = self::getReferralStudentIdentity($refereeInfo);
        // 获取奖励规则
        $ruleInfo = ReferralRulesModel::getCurrentRunRuleInfoByInviteStudentIdentity($refereeIdentity, ReferralRulesModel::TYPE_AI_STUDENT_REFEREE, $packageType);
        // 生成奖励
        self::createStudentTrialAward($packageType, $ruleInfo, $billInfo);

        return true;
    }

    /**
     * 获取邀请人(学生) 身份
     * @param $refereeInfo
     * @return int
     */
    public static function getReferralStudentIdentity($refereeInfo)
    {
        $time = time();
        switch ($refereeInfo['has_review_course']) {
            case DssStudentModel::REVIEW_COURSE_49:
                // 体验卡未过期
                $studentStatus = Constants::REFERRAL_INVITER_STATUS_TRAIL;
                // 体验卡过期未付费正式课
                if (strtotime($refereeInfo['sub_end_date']) < $time) {
                    $studentStatus = Constants::REFERRAL_INVITER_STATUS_TRAIL_EXPIRE;
                }
                break;
            case DssStudentModel::REVIEW_COURSE_1980:
                // 年卡未过期
                $studentStatus = Constants::REFERRAL_INVITER_STATUS_NORMAL;
                // 年卡过期未续费
                if (strtotime($refereeInfo['sub_end_date']) + Util::TIMESTAMP_ONEDAY < $time) {
                    $studentStatus = Constants::REFERRAL_INVITER_STATUS_NORMAL_EXPIRE;
                }
                break;
            default:
                // 仅注册
                $studentStatus = Constants::REFERRAL_INVITER_STATUS_REGISTER;
                break;
        }
        return $studentStatus;
    }

    /**
     * 2021.10.08 转介绍奖励规则 - 请勿直接调用，需要通过 self::createStudentRefereeAward 调用
     * 生成购买体验课包奖励
     * 规则以后台配置为准
     */
    private static function createStudentTrialAward($packageType, $ruleInfo, $billInfo)
    {
        $awardList = [];
        if (empty($ruleInfo) || empty($ruleInfo['rule_list'])) {
            throw new RunTimeException(["rule_empty"], [ $packageType, $ruleInfo]);
        }
        if (!in_array($packageType, [DssPackageExtModel::PACKAGE_TYPE_NORMAL, DssPackageExtModel::PACKAGE_TYPE_TRIAL])) {
            throw new RunTimeException(["package_type_error"], [ $packageType, $ruleInfo]);
        }
        // 循环处理奖励规则
        foreach ($ruleInfo['rule_list'] as $_rule) {
            if ($_rule['type'] != $packageType) {
                continue;
            }
            // 判断是否满足限制条件
            $_awardRestrictions = json_decode($_rule['restrictions'], true);
            // TODO qingfeng.lian 检查限制条件
            // 判断是否满足奖励条件
            $_awardCondition = json_decode($_rule['reward_condition'], true);
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
                // 年卡奖励条件， 产品包产品时长在指定时间范围内
                // TODO qingfeng.lian  时长判断
                if ($billInfo['valid_num'] >= 361) {

                }
            } else {
                // 体验卡奖励条件， 首次购买 - 这个在前面已经做了检查，这里不在重复检查
            }
            // 组装奖励明细
            $_awardInfo = json_decode($_rule['reward_details'], true);
        }
        unset($_rule);

        if (empty($awardList)) {
            SimpleLogger::info('no_patch_award', [$packageType, $ruleInfo]);
            return false;
        }
        // 生成待发放奖励
    }

    // 生成待发放奖励 - 金叶子
    // 生成待发放奖励 - 时长

    /**
     * 2021.10.08 转介绍奖励规则 - 请勿直接调用，需要通过 self::createStudentRefereeAward 调用
     * 生成购买正式课包奖励
     * 规则以后台配置为准
     */
    private static function createStudentNormalAward()
    {

    }
}
