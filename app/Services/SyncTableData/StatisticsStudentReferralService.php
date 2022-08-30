<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData;

use App\Libs\Exceptions\RunTimeException;
use App\Models\Erp\ErpReferralUserRefereeModel;
use App\Services\StudentService;
use App\Services\SyncTableData\TraitService\StatisticsStudentReferralBaseAbstract;

class StatisticsStudentReferralService extends StatisticsStudentReferralBaseAbstract
{
    /**
     * 更新转介绍统计数据
     * @param $appId
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function updateStatisticsStudentReferral($appId, $params): bool
    {
        $urId = $params['ur_id'];
        // 检查是否有推荐人
        $referralInfo = ErpReferralUserRefereeModel::getRecord(['id' => $urId]);
        // 没有推荐人不处理
        if (empty($referralInfo)) {
            return false;
        }
        // 获取用户信息
        $referralUser = StudentService::getUuid($appId, $referralInfo['referee_id'], ['uuid']);
        // 统计
        return StatisticsStudentReferralBaseAbstract::getAppObj($appId)
            ->updateStudentReferralStatistics($referralUser['uuid'], $params['action']);
    }

    // 检查用户是否是
}