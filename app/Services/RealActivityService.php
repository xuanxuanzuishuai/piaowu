<?php

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Models\OperationActivityModel;

class RealActivityService
{
    /**
     * 获取周周领奖和月月有奖活动海报列表
     * @param $studentId
     * @param $type
     * @param int $activityId
     * @param array $ext
     * @return array
     * @throws RunTimeException
     */
    public static function getWeekOrMonthActivityData($studentId, $type, $activityId = 0, $ext = [])
    {
        switch ($type) {
            case OperationActivityModel::TYPE_MONTH_ACTIVITY:
                $data = self::monthActivityData($studentId, $type, $activityId, $ext);
                break;
            case OperationActivityModel::TYPE_WEEK_ACTIVITY:
                $data = self::weekActivityData($studentId, $type, $activityId, $ext);
                break;
            default:
                throw new RunTimeException(["activity_type_is_error"]);
        }
        return $data;
    }

    /**
     * 获取周周领奖活动
     * @param $studentId
     * @param $type
     * @param int $activityId
     * @param array $ext
     * @return array
     */
    private static function weekActivityData($studentId, $type, $activityId = 0, $ext = [])
    {
        return [];
    }


    /**
     * 获取月月领奖活动
     * @param $studentId
     * @param $type
     * @param int $activityId
     * @param array $ext
     * @return array
     */
    private static function monthActivityData($studentId, $type, $activityId = 0, $ext = [])
    {
        return [];
    }

}
