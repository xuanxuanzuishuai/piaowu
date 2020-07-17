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

    const STATUS_INIT = 0; // 未发送
    const STATUS_SEND_SUCCESS = 1; // 发送成功
    const STATUS_SEND_FAILURE = 2; // 发送失败
    const EACH_LIMIT = 100; //每次插入100条

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

    /**
     * 获取指定日期已生成点评的学生id
     * @param $reviewDate
     * @return array
     */
    public static function existStudents($reviewDate)
    {
        $db = MysqlDB::getDB();
        return $db->select(self::$table, 'student_id', ['review_date' => $reviewDate]);
    }

    /**
     * 获取task
     * @param $where
     * @return array
     */
    public static function getTasks($where)
    {
        $db = MysqlDB::getDB();

        $table = self::$table . '(rct)';
        $join = [
            '[>]' . StudentModel::$table . '(s)' => ['rct.student_id' => 'id'],
            '[>]' . EmployeeModel::$table . '(e)' => ['rct.reviewer_id' => 'id']
        ];
        $columns = [
            'rct.id',
            'rct.review_date',
            'rct.play_date',
            'rct.sum_duration',
            'rct.status',
            's.id(student_id)',
            's.name(student_name)',
            's.mobile(student_mobile)',
            's.has_review_course(student_course_type)',
            'e.id(reviewer_id)',
            'e.name(reviewer_name)',
        ];

        $countWhere = $where;
        unset($countWhere['LIMIT']);
        $total = $db->count($table, $join, '*', $countWhere);
        if ($total < 1) {
            return [0, []];
        }

        $tasks = $db->select($table, $join, $columns, $where);
        return [$total, $tasks];
    }
}