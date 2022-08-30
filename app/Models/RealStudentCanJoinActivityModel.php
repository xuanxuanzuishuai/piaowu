<?php

namespace App\Models;


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
        if (empty($activityInfo)) {
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
            self::insertRecord($data);
            RealStudentCanJoinActivityHistoryModel::insertRecord([
                'student_uuid'            => $studentUuid,
                'activity_type'           => OperationActivityModel::SHARE_POSTER_ACTIVITY_TYPE_WEEK,
                'activity_id'             => $activityInfo['activity_id'],
                'week_progress'           => 1,
                'week_last_verify_status' => 0,
                'week_batch_update_day'   => date('Ymd'),
                'week_update_time'        => $time,
            ]);
        } else {
            unset($data['student_uuid'], $data['student_first_pay_time']);
            self::batchUpdateRecord($info['id'], $data);
        }
        return;
    }
}