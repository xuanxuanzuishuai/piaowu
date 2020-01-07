<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/7
 * Time: 4:08 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ReviewCourseTaskModel extends Model
{
    public static $table = 'review_course_task';

    /**
     * 检测指定日期是否已生成过点评任务
     * @param $reviewDate
     * @return bool
     */
    public static function hasTasks($reviewDate)
    {
        $db = MysqlDB::getDB();
        return $db->has(self::$table, ['review_date' => $reviewDate]);
    }
}