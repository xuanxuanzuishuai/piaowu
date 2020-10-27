<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/07/06
 * Time: 上午10:47
 */

namespace App\Models;


use App\Libs\MysqlDB;
use Medoo\Medoo;

class PointActivityRecordModel extends Model
{
    public static $table = 'point_activity_record';

    /**
     * 统计用户今日完成任务
     * @param $studentId
     * @param $taskId
     * @return int|mixed
     */
    public static function getStudentFinishTheTask($studentId, $taskId)
    {
        $where = ['student_id' => $studentId, 'report_date' => date('Y-m-d'), 'task_id' => $taskId];
        $result = self::getRecords($where);
        return $result ?? [];
    }
}