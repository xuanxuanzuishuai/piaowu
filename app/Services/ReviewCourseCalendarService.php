<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:54 PM
 */

namespace App\Services;


class ReviewCourseCalendarService
{
    /**
     * 根据点评工作日历获取指定日期该点评哪个时间段的演奏记录
     * @param $reviewDate
     * @return array
     */
    public static function getReviewTimeWindow($reviewDate)
    {
        $startTime = strtotime("$reviewDate -1 day");
        $endTime = $startTime + 86399;

        return [$startTime, $endTime];
    }
}