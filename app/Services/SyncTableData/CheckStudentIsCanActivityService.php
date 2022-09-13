<?php
/**
 * 检查学生是否能参与活动
 * author: qingfeng.lian
 * date: 2022/8/31
 */

namespace App\Services\SyncTableData;


use App\Models\RealStudentCanJoinActivityHistoryModel;
use App\Models\RealStudentCanJoinActivityModel;

class CheckStudentIsCanActivityService
{
    /**
     * 清除学生当前可参与活动，
     * 同时如果用户之前已经命中过该活动则标记学生当前活动参与状态为终止参与
     * @param $studentUuid
     * @param $activityId
     * @param $runTime
     * @return void
     */
    public static function cleanStudentWeekActivityId($studentUuid, $activityId, $runTime = 0)
    {
        if (empty($studentUuid)) {
            return;
        }
        $cleanWhere = ['student_uuid' => $studentUuid];
        if (!empty($cleanWhere)) $cleanWhere['week_update_time[<]'] = $runTime;
        RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId($cleanWhere);
        if (!empty($activityId)) {
            RealStudentCanJoinActivityHistoryModel::stopJoinWeekActivity($studentUuid, $activityId, $runTime);
        }
    }
}