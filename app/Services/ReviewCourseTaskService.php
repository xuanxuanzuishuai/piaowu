<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:08 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Models\PlayClassRecordModel;
use App\Models\ReviewCourseTaskModel;

class ReviewCourseTaskService
{
    /**
     * 生成指定日期的点评任务
     * @param $reviewDate
     * @return bool|null
     */
    public static function createDailyTasks($reviewDate)
    {
        if (ReviewCourseTaskModel::hasTasks($reviewDate)) {
            SimpleLogger::error("[createDailyTasks] tasks has been created", ['review_date' => $reviewDate]);
            return null;
        }

        list($startTime, $endTime) = ReviewCourseCalendarService::getReviewTimeWindow($reviewDate);
        if (empty($startTime) || empty($endTime)) {
            SimpleLogger::error("[createDailyTasks] invalid time window", [
                'review_date' => $reviewDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);
            return null;
        }

        $now = time();

        // 老师人数
        $reviewerIds = self::getReviewers();
        $reviewerCount = count($reviewerIds);

        if ($reviewerCount < 1) {
            SimpleLogger::error("[createDailyTasks] no reviewer", []);
            return null;
        }

        $playSum = PlayClassRecordModel::studentDailySum($startTime, $endTime);
        $newTasks = [];
        $last = -1;
        foreach ($playSum as $data) {
            // 同一个人不同日期的数据只取最时间最长的一天
            if ($last >= 0 && $newTasks[$last]['student_id'] == $data['student_id']) {
                if ($data['sum_duration'] > $newTasks[$last]['sum_duration']) {
                    $newTasks[$last]['sum_duration'] = $data['sum_duration'];
                    $newTasks[$last]['play_date'] = $data['play_date'];
                }
                continue;
            }

            $newTasks[] = [
                'student_id' => $data['student_id'],
                'review_date' => $reviewDate,
                'play_date' => $data['play_date'],
                'sum_duration' => $data['sum_duration'],
                'reviewer_id' => $reviewerIds[($last + 1) % $reviewerCount],
                'create_time' => $now,
                'status' => Constants::STATUS_FALSE,
                'update_time' => 0,
                'review_audio' => '',
            ];

            $last++;
        }

        $success = ReviewCourseTaskModel::batchInsert($newTasks, false);
        return $success ? count($newTasks) : 0;
    }

    /**
     * 获取点评老师id
     * @return array
     */
    public static function getReviewers()
    {
        $reviewerSetting = DictConstants::get(DictConstants::REVIEW_COURSE_CONFIG, 'reviewer_ids');
        $reviewerIds = explode(',', $reviewerSetting);
        return $reviewerIds;
    }
}