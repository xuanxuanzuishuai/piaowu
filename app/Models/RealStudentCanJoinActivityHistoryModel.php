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
                'week_progress'         => Constants::STUDENT_WEEK_PROGRESS_STOP_JOIN,
                'week_batch_update_day' => date("Ymd"),
                'week_update_time'      => time(),
            ],
            $where
        );
    }
}