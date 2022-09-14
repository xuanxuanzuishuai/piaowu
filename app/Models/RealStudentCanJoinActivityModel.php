<?php

namespace App\Models;


use Medoo\Medoo;

class RealStudentCanJoinActivityModel extends Model
{
    public static $table = "real_student_can_join_activity";

    /**
     * 清除所有学生周周领奖活动id
     * @param $where
     * @return void
     */
    public static function cleanAllStudentWeekActivityId($where = [])
    {
        self::batchUpdateRecord(
            [
                'week_activity_id'         => 0,
                'week_activity_start_time' => 0,
                'week_activity_end_time'   => 0,
                'week_task_num'            => 0,
                'week_join_num'            => 0,
                'week_last_verify_status'  => 0,
                'week_progress'            => 0,
                'week_batch_update_day'    => date("Ymd"),
                'week_update_time'         => time(),
            ],
            $where
        );
    }

    /**
     * 更新学生命中的活动
     * @param $studentInfo
     * @param $activityInfo
     * @return void
     */
    public static function updateStudentHitWeekActivity($studentInfo, $activityInfo)
    {
        if (empty($activityInfo) || empty($studentInfo)) {
            return;
        }
        $time = time();
        $studentUuid = $studentInfo['uuid'];
        $data = [
            'student_uuid'             => $studentUuid,
            'student_first_pay_time'   => $studentInfo['first_pay_time'],
            'week_activity_id'         => $activityInfo['activity_id'],
            'week_activity_start_time' => $activityInfo['start_time'],
            'week_activity_end_time'   => $activityInfo['end_time'],
            'week_task_num'            => $activityInfo['activity_task_total'],
            'week_join_num'            => 0,
            'week_last_verify_status'  => 0,
            'week_progress'            => 1,
            'week_batch_update_day'    => date('Ymd'),
            'week_update_time'         => $time,
        ];
        $info = self::getRecord(['student_uuid' => $studentUuid]);
        if (empty($info)) {
            // 用户首次命中活动
            self::insertRecord($data);
        } else {
            unset($data['student_uuid'], $data['student_first_pay_time']);
            self::updateRecord($info['id'], $data);
        }

        // 历史记录中不存在则新增，存在更新
        RealStudentCanJoinActivityHistoryModel::createOrUpdateHitWeek($studentUuid, $activityInfo['activity_id'], [
            'student_uuid'         => $studentUuid,
            'activity_type'        => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK,
            'activity_id'          => $activityInfo['activity_id'],
            'activity_start_time'  => $activityInfo['start_time'],
            'activity_end_time'    => $activityInfo['end_time'],
            'task_num'             => $activityInfo['activity_task_total'],
            'join_num'             => 0,
            'last_verify_status'   => 0,
            'update_time'          => $time,
            'activity_status'      => $activityInfo['enable_status'],
            'activity_create_time' => $activityInfo['create_time'],
        ]);
    }

    /**
     * 更新学生最后一次参与审核状态为拒绝
     * @param $studentUuid
     * @param $activityId
     * @return bool
     */
    public static function updateLastVerifyStatusIsRefused($studentUuid, $activityId)
    {
        self::batchUpdateRecord(
            ['week_last_verify_status' => RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED],
            ['student_uuid' => $studentUuid, 'week_activity_id' => $activityId]
        );
        RealStudentCanJoinActivityHistoryModel::batchUpdateRecord(
            ['last_verify_status' => RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED],
            ['student_uuid' => $studentUuid, 'activity_id' => $activityId]
        );
        return true;
    }

    /**
     * 更新学生最后一次参与审核状态为通过
     * 同时更新参与进度
     * @param $studentUuid
     * @param $activityId
     * @param $studentJoinActivityInfo
     * @return bool
     */
    public static function updateLastVerifyStatusIsPass($studentUuid, $activityId, $studentJoinActivityInfo = [])
    {
        if (empty($studentJoinActivityInfo)) {
            $studentJoinActivityInfo = self::getRecord(['student_uuid' => $studentUuid, 'week_activity_id' => $activityId]);
        }
        $joinProgress = RealStudentCanJoinActivityHistoryModel::computeJoinProgress($studentJoinActivityInfo);
        self::batchUpdateRecord(
            ['week_last_verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED, 'week_join_num' => Medoo::raw("week_join_num+1"), 'week_progress' => $joinProgress],
            ['student_uuid' => $studentUuid, 'week_activity_id' => $activityId]
        );
        RealStudentCanJoinActivityHistoryModel::batchUpdateRecord(
            ['last_verify_status' => RealSharePosterModel::VERIFY_STATUS_QUALIFIED, 'join_num' => Medoo::raw("join_num+1"), 'join_progress' => $joinProgress],
            ['student_uuid' => $studentUuid, 'activity_id' => $activityId]
        );
        return true;
    }
}