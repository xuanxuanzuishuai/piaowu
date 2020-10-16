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
     * 统计用户今日完成任务总数
     * @param $studentId
     * @param $taskId
     * @return int|mixed
     */
    public static function getStudentFinishTheTask($studentId, $taskId)
    {
        $columns = [
            'finish_the_task' => Medoo::raw('COUNT(DISTINCT(task_id))'),
        ];
        $where = ['student_id' => $studentId, 'report_date' => date('Y-m-d'), 'task_id' => $taskId];

        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, $columns, $where);
        return $result['finish_the_task'] ?? 0;
    }
}