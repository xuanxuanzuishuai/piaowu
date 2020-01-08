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
use App\Libs\Util;
use App\Models\EmployeeModel;
use App\Models\PlayClassRecordModel;
use App\Models\ReviewCourseModel;
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

    /**
     * task 列表
     * @param $params
     * @return array
     */
    public static function getTasks($params)
    {
        $where = [];

        if (!empty($params['reviewer_id'])) {
            $where['e.id'] = $params['reviewer_id'];
        }

        if (!empty($params['review_after'])) {
            $where['review_date[>=]'] = $params['review_after'];
        }
        if (!empty($params['review_before'])) {
            $where['review_date[<=]'] = $params['review_before'];
        }

        if (!empty($params['play_after'])) {
            $where['play_date[>=]'] = $params['play_after'];
        }
        if (!empty($params['play_before'])) {
            $where['play_date[<=]'] = $params['play_before'];
        }

        if (!empty($params['student_id'])) {
            $where['s.id'] = $params['student_id'];
        }

        if (!empty($params['student_mobile'])) {
            $where['s.mobile'] = $params['student_mobile'];
        }

        if (!empty($params['student_name'])) {
            $where['s.name[~]'] = Util::sqlLike($params['student_name']);
        }

        if (!empty($params['student_course_type'])) {
            $where['s.has_review_course'] = $params['student_course_type'];
        } else {
            $where['s.has_review_course'] = [ReviewCourseModel::REVIEW_COURSE_49, ReviewCourseModel::REVIEW_COURSE_1980];
        }

        if (isset($params['status'])) {
            $where['rct.status'] = $params['status'];
        }

        list($page, $count) = Util::formatPageCount($params);
        $where['LIMIT'] = [($page - 1) * $count, $count];

        $result = ReviewCourseTaskModel::getTasks($where);

        return $result;
    }

    /**
     * 获取点评课相关配置
     * @return array
     */
    public static function getConfig()
    {
        $courseTypes = [
            ReviewCourseModel::REVIEW_COURSE_49 => '49课包',
            ReviewCourseModel::REVIEW_COURSE_1980 => '1980课包'
        ];

        $reviewerIds = self::getReviewers();
        $reviewers = EmployeeModel::getRecords(['id' => $reviewerIds], ['id', 'name'], false);
        $reviewers = array_column($reviewers, 'name', 'id');

        return [
            'student_course_types' => $courseTypes,
            'reviewers' => $reviewers,
        ];
    }
}