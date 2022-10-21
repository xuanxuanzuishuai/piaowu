<?php

namespace App\Models;


use App\Libs\Constants;

class RealStudentCanJoinActivityHistoryModel extends Model
{
    public static $table = "real_student_can_join_activity_history";

    /**
     * 停止学生周周领奖活动参与进度
     * @param $where
     * @return void
     */
    public static function stopStudentJoinWeekActivityProgress($where = [])
    {
        self::batchUpdateRecord(
            [
                'join_progress'    => Constants::STUDENT_WEEK_PROGRESS_STOP_JOIN,
                'batch_update_day' => date("Ymd"),
                'update_time'      => time(),
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
            $joinProgress = $update['join_progress'] ?? self::computeJoinProgress($hasHistory);
            self::updateRecord($hasHistory['id'], [
                'join_progress'    => $joinProgress,
                'activity_status'  => $update['activity_status'],
                'batch_update_day' => $update['batch_update_day'] ?? date('Ymd'),
                'update_time'      => $update['update_time'] ?? time(),
            ]);
        }
        return true;
    }

    /**
     * 新增一条命中周周领奖记录
     * @param $studentUuid
     * @param $update
     * @return bool
     */
    public static function createHitWeek($studentUuid, $update)
    {
        self::insertRecord([
            'student_uuid'         => $studentUuid,
            'activity_type'        => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK,
            'activity_id'          => $update['activity_id'],
            'activity_start_time'  => $update['activity_start_time'],
            'activity_end_time'    => $update['activity_end_time'],
            'task_num'             => $update['task_num'],
            'join_num'             => $update['join_num'] ?? 0,
            'join_progress'        => 1,
            'last_verify_status'   => $update['last_verify_status'] ?? RealStudentCanJoinActivityModel::LAST_VERIFY_STATUS_NO_UPLOAD,
            'batch_update_day'     => $update['batch_update_day'] ?? date('Ymd'),
            'update_time'          => $update['update_time'] ?? time(),
            'activity_status'      => $update['activity_status'],
            'activity_create_time' => $update['activity_create_time'],
        ]);
        return true;
    }

    /**
     * 标记学生指定活动参与状态为"终止参与"
     * 终止参与： 表示用户在活动进行中用户不再满足参与条件
     * @param $studentUuid
     * @param $activityId
     * @param int $runTime
     * @return void
     */
    public static function stopJoinWeekActivity($studentUuid, $activityId, $runTime = 0)
    {
        $where = [
            'student_uuid'     => $studentUuid,
            'activity_id'      => $activityId,
            'join_progress[!]' => Constants::STUDENT_WEEK_PROGRESS_COMPLETE_JOIN,
        ];
        if (!empty($runTime)) $where['update_time[<]'] = $runTime;
        self::batchUpdateRecord(
            [
                'join_progress' => Constants::STUDENT_WEEK_PROGRESS_STOP_JOIN,
            ],
            $where
        );
    }

    /**
     * 获取学生参与活动历史记录
     * @param $studentUuid
     * @param $otherWhere
     * @return array
     */
    public static function getStudentJoinActivityHistory($studentUuid, $otherWhere)
    {
        $returnData = [
            // 'total_count' => 0,
            'list' => [],
        ];
        $where = [
            'student_uuid' => $studentUuid,
        ];
        if (!empty($otherWhere['activity_id'])) $where['activity_id'] = $otherWhere['activity_id'];
        if (!empty($otherWhere['join_progress'])) $where['join_progress'] = $otherWhere['join_progress'];
        if (!empty($otherWhere['last_verify_status'])) $where['last_verify_status'] = $otherWhere['last_verify_status'];
        $returnData['total_count'] = self::getCount($where);
        if (empty($returnData['total_count'])) {
            return $returnData;
        }

        if (!empty($otherWhere['count'])) {
            $page = $otherWhere['page'] ?? 1;
            $where['LIMIT'] = [($page - 1) * $otherWhere['count'], $otherWhere['count']];
        }
        if (!empty($otherWhere['ORDER'])) $where['ORDER'] = $otherWhere['ORDER'];
        $returnData['list'] = self::getRecords($where);
        return $returnData;
    }

    /**
     * 计算活动参与进度
     * @param $joinActivityInfo
     * @return int
     */
    public static function computeJoinProgress($joinActivityInfo)
    {
        if ($joinActivityInfo['join_num'] > 0) {
            if ($joinActivityInfo['task_num'] == $joinActivityInfo['join_num']) {
                $joinProgress = Constants::STUDENT_WEEK_PROGRESS_COMPLETE_JOIN;
            } else {
                $joinProgress = Constants::STUDENT_WEEK_PROGRESS_JOINING;
            }
        } else {
            $joinProgress = Constants::STUDENT_WEEK_PROGRESS_NO_JOIN;
        }
        return $joinProgress;
    }
}