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
    public static function cleanStudentWeekActivityId($studentUuid, $activityId)
    {
        if (empty($studentUuid)) {
            return;
        }
        RealStudentCanJoinActivityModel::cleanAllStudentWeekActivityId(['student_uuid' => $studentUuid]);
        if (!empty($activityId)) {
            RealStudentCanJoinActivityHistoryModel::stopJoinWeekActivity($studentUuid, $activityId);
        }
    }
}