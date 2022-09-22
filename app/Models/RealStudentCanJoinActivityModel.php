<?php

namespace App\Models;


use Medoo\Medoo;

class RealStudentCanJoinActivityModel extends Model
{
    public static $table = "real_student_can_join_activity";

    // 最后审核状态  1待审核 2合格 3不合格 -1未发圈
    const  LAST_VERIFY_STATUS_WAIT = RealSharePosterModel::VERIFY_STATUS_WAIT;
    const  LAST_VERIFY_STATUS_QUALIFIED = RealSharePosterModel::VERIFY_STATUS_QUALIFIED;
    const  LAST_VERIFY_STATUS_UNQUALIFIED = RealSharePosterModel::VERIFY_STATUS_UNQUALIFIED;
    const  LAST_VERIFY_STATUS_NO_UPLOAD = -1;

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
                'week_last_verify_status'  => self::LAST_VERIFY_STATUS_NO_UPLOAD,
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
            'week_last_verify_status'  => self::LAST_VERIFY_STATUS_NO_UPLOAD,
            'week_progress'            => 1,
            'week_batch_update_day'    => date('Ymd'),
            'week_update_time'         => $time,
        ];
        $info = self::getRecord(['student_uuid' => $studentUuid]);
        // 命中的活动没有变化只更新时间
        if (!empty($info) && $info['week_activity_id'] == $activityInfo['activity_id']) {
            self::updateWeekUpdateTime($studentUuid, $activityInfo['activity_id'], $time);
            return;
        }
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
            'last_verify_status'   => self::LAST_VERIFY_STATUS_NO_UPLOAD,
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
            $studentJoinActivityInfo = RealStudentCanJoinActivityHistoryModel::getRecord(['student_uuid' => $studentUuid, 'activity_id' => $activityId]);
        }
        if (empty($studentJoinActivityInfo)) {
            return false;
        }
        $studentJoinActivityInfo['join_num'] +=1;
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

    /**
     * 更新学生最后一次参与审核状态为待审核
     * @param $studentUuid
     * @param $activityId
     * @return bool
     */
    public static function updateLastVerifyStatusIsWait($studentUuid, $activityId)
    {
        self::batchUpdateRecord(
            ['week_last_verify_status' => self::LAST_VERIFY_STATUS_WAIT],
            ['student_uuid' => $studentUuid, 'week_activity_id' => $activityId]
        );
        RealStudentCanJoinActivityHistoryModel::batchUpdateRecord(
            ['last_verify_status' => self::LAST_VERIFY_STATUS_WAIT],
            ['student_uuid' => $studentUuid, 'activity_id' => $activityId]
        );
        return true;
    }

    /**
     * 只更新周周领奖的最后操作时间和日期
     * @param $studentUuid
     * @param $activityId
     * @param $updateTime
     * @return bool
     */
    public static function updateWeekUpdateTime($studentUuid, $activityId, $updateTime = 0)
    {
        $updateTime <= 0 && $updateTime = time();
        self::batchUpdateRecord(
            ['week_batch_update_day' => date("Ymd", $updateTime),'week_update_time' => $updateTime],
            ['student_uuid' => $studentUuid, 'week_activity_id' => $activityId]
        );
        RealStudentCanJoinActivityHistoryModel::batchUpdateRecord(
            ['batch_update_day' => date("Ymd", $updateTime),'update_time' => $updateTime],
            ['student_uuid' => $studentUuid, 'activity_id' => $activityId]
        );
        return true;
    }
}