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

    /**
     * 获取学生任务完成数量
     * @param $studentId
     * @param $startTime
     * @param $endTime
     * @param $taskId
     * @return array|null
     */
    public static function getStudentFinishTasksBetweenTime($studentId, $startTime, $endTime, $taskId)
    {
        $db = MysqlDB::getDB();
        $map = [
            ':start_time' => $startTime,
            ':end_time' => $endTime];
        $result = $db->queryAll('SELECT
                                    count( * ) as cm,
                                    ta.student_id,
                                    ta.task_id
                                FROM
                                    (
                                    SELECT
                                        student_id,
                                        task_id,
                                        report_date
                                    FROM
                                        point_activity_record
                                    WHERE
                                        student_id IN ( '.implode(',',$studentId).' )
                                        AND task_id IN ( '.implode(',',$taskId).' )
                                        AND create_time BETWEEN :start_time
                                        AND :end_time
                                    GROUP BY
                                        student_id,
                                        task_id,
                                        report_date
                                    ) AS ta
                                GROUP BY
                                    ta.student_id,
                                    ta.task_id', $map);
        return $result;
    }

    /**
     * 获取学生完成的新手任务
     * @param $studentId
     * @param $taskId
     * @return array
     */
    public static function getStudentFinishNoviceActivity($studentId, $taskId)
    {
        $table = self::$table;
        $sql = "select * from {$table} where student_id = :student_id and task_id in (". $taskId .")";
        $result = MysqlDB::getDB()->queryAll($sql, [':student_id' => $studentId]);
        return $result ?? [];
    }

}