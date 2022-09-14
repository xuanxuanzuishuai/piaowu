<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData;

use App\Libs\Exceptions\RunTimeException;
use App\Services\StudentService;
use App\Services\SyncTableData\TraitService\StatisticsStudentReferralBaseAbstract;

class StatisticsStudentReferralService extends StatisticsStudentReferralBaseAbstract
{
    /**
     * 更新转介绍统计数据
     * @param $appId
     * @param array $referralInfo erp库中referral_user_referee 表数据
     * @return bool
     * @throws RunTimeException
     */
    public static function updateStatisticsStudentReferral($appId, $referralInfo): bool
    {
        // 没有数据不处理
        if (empty($referralInfo)) {
            return true;
        }
        // 获取用户信息
        $referralUser = StudentService::getUuid($appId, $referralInfo['referee_id'], ['uuid']);
        // 统计
        StatisticsStudentReferralBaseAbstract::getAppObj($appId)->updateStudentReferralStatistics($referralUser['uuid']);
        return true;
    }

    // 检查用户是否是
}