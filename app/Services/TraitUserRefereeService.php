<?php
/**
 * 用户推荐奖励
 */

namespace App\Services;

use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;

trait TraitUserRefereeService
{
    /**
     * 获取学生转介绍推荐奖励方法统一入口
     * @param $packageType
     * @param $refereeInfo
     * @param $parentBillId
     * @return mixed
     */
    public static function getStudentRefereeAwardTaskId($packageType, $refereeInfo, $parentBillId)
    {
        return self::XYZOP1230($packageType, $refereeInfo, $parentBillId);
    }

    /**
     * 2021.09.29 转介绍奖励规则 - 请勿直接调用，需要通过 self::getStudentRefereeAwardTaskId 调用
     * 邀请人(年卡未过期) && 受邀人(购买年卡并且年卡大于等于361天)  奖励：邀请人(40000金叶子)， 受邀人(没有奖励)
     * @param $packageType
     * @param $refereeInfo
     * @param $parentBillId
     * @return array
     */
    private static function XYZOP1230($packageType, $refereeInfo, $parentBillId): array
    {
        $taskIds = [];
        $time = time();
        // 年卡未过期推荐人
        if ($refereeInfo['has_review_course'] == DssStudentModel::REVIEW_COURSE_1980 && strtotime($refereeInfo['sub_end_date']) + Util::TIMESTAMP_ONEDAY >= $time) {
            if ($packageType == DssPackageExtModel::PACKAGE_TYPE_NORMAL) {
                // 获取订单的时长
                $totalInfo = DssGiftCodeModel::getRecord(['parent_bill_id' => $parentBillId], ['id', 'valid_num', 'valid_units']);
                if (empty($totalInfo)) {
                    SimpleLogger::info("parent_bill_id_is_not_found", [$parentBillId, $packageType, $refereeInfo, $totalInfo]);
                    return $taskIds;
                }
                if ($totalInfo['valid_num'] >= 361) {
                    $taskIds[] = [
                        'task_id' => RefereeAwardService::getDssYearPayTaskId(),
                    ];
                }
            }
        }
        return  $taskIds;
    }
}
