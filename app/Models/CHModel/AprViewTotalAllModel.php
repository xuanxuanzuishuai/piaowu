<?php
/**
 * Created by PhpStorm.
 * User: yangpeng
 * Date: 2021-09-08 18:00:39
 */

namespace App\Models\CHModel;


use App\Libs\CHDB;

class AprViewTotalAllModel extends CHOBModel
{

    public static $table = "apr_view_total_all";
    
    /**
     * 统计学生练琴天数&曲子数
     * @param int $studentId
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    public static function getStudentLessonCountAndData(int $studentId): array
    {
        $chDb = CHDB::getBODB();
        $sql = 'SELECT uniqMerge(lesson_ids) as lesson_count, uniqMerge(days) as play_day
                FROM '. self::$table .' WHERE student_id = '.$studentId .' GROUP BY student_id';

        $playSumData = $chDb->queryAll($sql);
        return $playSumData[0] ?? [];
    }
}
