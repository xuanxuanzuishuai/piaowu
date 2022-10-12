<?php
/**
 * 清晨任务奖励管理
 * author: qingfeng.lian
 * date: 2022/10/14
 */

namespace App\Services\MorningReferral;

use App\Libs\Exceptions\RunTimeException;
use App\Models\Erp\ErpEventTaskModel;
use App\Models\MorningTaskAwardModel;
use App\Services\Queue\MorningReferralTopic;
use App\Services\Queue\QueueService;

class MorningTaskAwardActivityManageService
{

    /**
     * 更新红包发放进度
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function redPackUpdateStatus($params)
    {
        $taskAwardId = $params['task_award_id'] ?? 0;
        $operationId = $params['employee_id'] ?? 0;
        $reason = $params['reason'] ?? '';
        $status = $params['status'] ?? 0;
        $activityType = $params['activity_type'] ?? MorningTaskAwardModel::MORNING_ACTIVITY_TYPE;
        if (empty($taskAwardId) || empty($operationId)) {
            throw new RunTimeException(['invalid_award_id_or_reviewer_id']);
        }
        $now = time();
        $taskAwardIds = array_unique(explode(',', $taskAwardId));
        if (count($taskAwardIds) > 50) {
            throw new RunTimeException(['over_max_allow_num']);
        }
        // 查询记录
        $awardRecordList = MorningTaskAwardModel::getRecords([
            'id'            => $taskAwardIds,
            'activity_type' => $activityType,
            'award_type'    => ErpEventTaskModel::AWARD_TYPE_CASH,
        ]);
        if (empty($awardRecordList)) {
            return true;
        }

        foreach ($awardRecordList as $item) {
            if (!in_array($item['status'], [MorningTaskAwardModel::STATUS_GIVE, MorningTaskAwardModel::STATUS_GIVE_ING])) {
                // 发放成功或发放中待领取的不能更新操作状态
                continue;
            }
            if ($status == MorningTaskAwardModel::STATUS_GIVE_ING) {
                $_sendData = [
                    'student_uuid'           => $item['student_uuid'],
                    'task_award_id'          => $item['id'],
                    'share_poster_record_id' => $item['award_from'],
                ];
                QueueService::morningPushMsg(MorningReferralTopic::EVENT_CLOCK_ACTIVITY_SEND_RED_PACK, $_sendData, rand(0, 5));
            } else {
                // 更新为不发放
                MorningTaskAwardModel::updateStatusIsDisabled($item['id'], $reason, $operationId);
            }
        }
        return true;
    }

    /**
     * 红包审核列表
     * @param $params
     * @return array
     */
    public static function redPackList($params)
    {
        $returnData = ['total_count' => 0, 'list' => []];
        return $returnData;
    }
}