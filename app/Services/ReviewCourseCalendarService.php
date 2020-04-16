<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:54 PM
 */

namespace App\Services;


use App\Libs\Constants;
use App\Libs\SimpleLogger;
use App\Models\ReviewCourseCalendarModel;

class ReviewCourseCalendarService
{
    /**
     * 根据点评工作日历获取指定日期该点评哪个时间段的演奏记录
     * @param $reviewDate
     * @return array
     */
    public static function getReviewTimeWindow($reviewDate)
    {
        // 结束时间为点评日期当天0点
        $endTime = strtotime($reviewDate);
        if (empty($endTime)) {
            SimpleLogger::error('getReviewTimeWindow: error review date', ['$reviewDate' => $reviewDate]);
            return [null, null];
        }

        $dateConfig = ReviewCourseCalendarModel::getRecord(
            ['review_date' => $reviewDate, 'status' => Constants::STATUS_TRUE], '*', false);
        $dayOfWeek = date("w");

        SimpleLogger::debug('getReviewTimeWindow: date config', [
            '$dateConfig' => $dateConfig,
            '$dayOfWeek' => $dayOfWeek
        ]);

        if (!empty($dateConfig)) {

            // 开始时间默认为设置的点评日期前一天0点
            $startTime = strtotime($dateConfig['play_date']);

            // play_date 设置为0时表示对应的 review_date 为休息日不点评
            if (empty($startTime)) {
                SimpleLogger::debug('getReviewTimeWindow: return null, play_date = 0', []);
                return [null, null];
            }

        } /* 移除周五六日合并到周一点评的逻辑 elseif ($dayOfWeek == 6 || $dayOfWeek == 0) {
            // 周末休息不点评
            SimpleLogger::debug('getReviewTimeWindow: return null, weekend', []);
            return [null, null];

        } elseif ($dayOfWeek == 1) {
            // 周一点评开始时间为周五(前三前)0点
            $startTime = $endTime - (86400*3);

        }*/ else {
            // 开始时间默认为点评日期前一天0点
            $startTime = $endTime - 86400;
        }

        return [$startTime, $endTime-1];
    }
}