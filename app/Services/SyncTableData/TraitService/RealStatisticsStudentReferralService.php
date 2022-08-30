<?php
/**
 * author: qingfeng.lian
 * date: 2022/8/29
 */

namespace App\Services\SyncTableData\TraitService;


use App\Libs\SimpleLogger;
use App\Models\RealStudentReferralInfoModel;
use ClickHouseDB\Exception\QueryException;
use Medoo\Medoo;

class RealStatisticsStudentReferralService extends StatisticsStudentReferralBaseAbstract
{
    /**
     * 更新推荐的学员购买体验卡和年卡的数量
     * @param $studentUuid
     * @param $action
     * @return bool
     */
    public function updateStudentReferralStatistics($studentUuid, $action): bool
    {
        $info = RealStudentReferralInfoModel::getRecord(['student_uuid' => $studentUuid]);
        // 不存在转介绍关系-创建转介绍关系
        if (empty($info)) {
            SimpleLogger::info('RealStatisticsStudentReferralService::updateStudentReferralStatistics', [
                'msg'          => 'not found referral',
                'info'         => $info,
                'student_uuid' => $studentUuid,
            ]);
            try {
                RealStudentReferralInfoModel::insertRecord([
                    'student_uuid' => $studentUuid,
                ]);
            } catch (QueryException $e) {
                SimpleLogger::info('RealStatisticsStudentReferralService::updateStudentReferralStatistics', [
                    'msg' => 'create real student referral info error',
                    'err' => $e->getMessage(),
                ]);
            }
        }
        // 存在转介绍关系，购买的年卡，更新
        if ($action == 4) {
            $update = [
                'now_referral_trail_num'  => Medoo::raw("now_referral_trail_num-1"),
                'now_referral_normal_num' => Medoo::raw("now_referral_normal_num+1"),
            ];
        } elseif ($action == 1) {
            $update = [
                'referral_num'           => Medoo::raw("referral_num+1"),
                'now_referral_trail_num' => Medoo::raw("now_referral_trail_num+1"),
            ];
        } else {
            SimpleLogger::info('RealStatisticsStudentReferralService::updateStudentReferralStatistics', [
                'msg' => 'action not handle',
            ]);
            return true;
        }
        RealStudentReferralInfoModel::batchUpdateRecord($update, ['student_uuid' => $studentUuid]);
        return true;
    }
}