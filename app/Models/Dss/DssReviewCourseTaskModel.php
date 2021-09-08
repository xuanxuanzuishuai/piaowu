<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:08 PM
 */

namespace App\Models\Dss;


use App\Models\Dss\DssModel;

class DssReviewCourseTaskModel extends DssModel
{
    public static $table = 'review_course_task';

    const STATUS_INIT = 0; // 未发送
    const STATUS_SEND_SUCCESS = 1; // 发送成功
    const STATUS_SEND_FAILURE = 2; // 发送失败
    const EACH_LIMIT = 100; //每次插入100条

    /**
     * 获取学生练琴时长数据
     * @param $where
     * @return number
     */
    public static function getStudentReviewDuration($where)
    {
        return self::dbRO()->sum(self::$table, 'sum_duration', $where);
    }
}