<?php

namespace App\Models;


use App\Libs\Constants;

class RealStudentCanJoinActivityHistoryModel extends Model
{
    public static $table = "real_student_can_join_activity_history";

    const JOIN_PROGRESS_NO        = 1; // 未参与
    const JOIN_PROGRESS_NO_FINISH = 2; // 参与未完成
    const JOIN_PROGRESS_FINISH    = 3; // 已完成
    const JOIN_PROGRESS_STOP      = 4; // 资格终止

    /**
     * 停止学生周周领奖活动参与进度
     * @param $where
     * @return void
     */
    public static function stopStudentJoinWeekActivityProgress($where = [])
    {
        self::batchUpdateRecord(
            [
                'week_progress'         => Constants::STUDENT_WEEK_PROGRESS_STOP_JOIN,
                'week_batch_update_day' => date("Ymd"),
                'week_update_time'      => time(),
            ],
            $where
        );
    }

    /**
     * 查询学生指定活动的历史记录
     * @param $studentUuid
     * @param $activityId
     * @return array|mixed
     */
    public static function getStudentWeekActivityHistory($studentUuid, $activityId)
    {
        $info = self::getRecord(['student_uuid' => $studentUuid, 'activity_id' => $activityId, 'activity_type' => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK]);
        return is_array($info) ? $info : [];
    }

    /**
     * 创建或者更新命中的周周领奖活动
     * 存在记录：更新
     * 不存在记录：新增
     * @param $studentUuid
     * @param $activityId
     * @param $update
     * @param $hasHistory
     * @return bool
     */
    public static function createOrUpdateHitWeek($studentUuid, $activityId, $update, $hasHistory = [])
    {
        if (empty($hasHistory)) {
            $hasHistory = self::getRecord(['student_uuid' => $studentUuid, 'activity_id' => $activityId, 'activity_type' => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK]);
        }
        if (empty($hasHistory)) {
            $update['student_uuid'] = $studentUuid;
            $update['activity_id'] = $activityId;
            $update['type'] = OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK;
            self::createHitWeek($studentUuid, $update);
        } else {
            $joinProgress = $update['join_progress'] ?? 0;
            if (empty($joinProgress)) {
                if ($hasHistory['join_num'] > 0) {
                    if ($hasHistory['task_num'] == $hasHistory['join_num']) {
                        $joinProgress = self::JOIN_PROGRESS_FINISH;
                    } else {
                        $joinProgress = self::JOIN_PROGRESS_NO_FINISH;
                    }
                } else {
                    $joinProgress = self::JOIN_PROGRESS_NO;
                }
            }
            self::updateRecord($hasHistory['id'], [
                'join_progress'    => $joinProgress,
                'batch_update_day' => $update['batch_update_day'] ?? date('Ymd'),
                'update_time'      => $update['update_time'] ?? time(),
            ]);
        }
        return true;
    }

    public static function createHitWeek($studentUuid, $update)
    {
        self::insertRecord([
            'student_uuid'        => $studentUuid,
            'activity_type'       => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK,
            'activity_id'         => $update['activity_id'],
            'activity_start_time' => $update['activity_start_time'],
            'activity_end_time'   => $update['activity_end_time'],
            'task_num'            => $update['task_num'],
            'join_num'            => $update['join_num'] ?? 0,
            'join_progress'       => 1,
            'last_verify_status'  => 0,
            'batch_update_day'    => $update['batch_update_day'] ?? date('Ymd'),
            'update_time'         => $update['update_time'] ?? time(),
        ]);
        return true;
    }

    public static function stopJoinWeekActivity($studentUuid, $activityId)
    {
        self::batchUpdateRecord(
            [
                'join_progress' => self::JOIN_PROGRESS_STOP,
            ],
            [
                'student_uuid' => $studentUuid,
                'activity_id'  => $activityId,
            ]
        );
    }
}