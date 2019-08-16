<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;


use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;

class HomeworkModel extends Model
{
    public static $table = "homework";


    /** 创建
     * @param $schedule_id
     * @param $org_id
     * @param $teacher_id
     * @param $student_id
     * @param $created_time
     * @param $end_time
     * @param $remark
     * @return int|mixed|null|string
     */
    public static function createHomework($schedule_id, $org_id, $teacher_id, $student_id, $created_time, $end_time, $remark)
    {
        return MysqlDB::getDB()->insertGetID(self::$table, [
            'schedule_id' => $schedule_id,
            'org_id' => $org_id,
            'created_time' => $created_time,
            'teacher_id' => $teacher_id,
            'student_id' => $student_id,
            'end_time' => $end_time,
            'remark' => $remark
        ]);
    }


    /**
     * 查询作业
     * @param array $where
     * @return array
     */
    public static function getHomeworkList($where = [])
    {
        $result = MysqlDB::getDB()->select(HomeworkModel::$table, [
            '[>]' . HomeworkTaskModel::$table => ['id' => 'homework_id'],
            '[>]' . TeacherModel::$table => [self::$table . '.teacher_id' => 'id']
        ], [
            HomeworkModel::$table . '.id',
            HomeworkModel::$table . '.student_id',
            HomeworkModel::$table . '.teacher_id',
            HomeworkModel::$table . '.org_id',
            HomeworkModel::$table . '.schedule_id',
            HomeworkModel::$table . '.created_time',
            HomeworkModel::$table . '.end_time',
            HomeworkModel::$table . '.remark',
            HomeworkTaskModel::$table . '.id(task_id)',
            HomeworkTaskModel::$table . '.lesson_id',
            HomeworkTaskModel::$table . '.lesson_name',
            HomeworkTaskModel::$table . '.collection_id',
            HomeworkTaskModel::$table . '.collection_name',
            HomeworkTaskModel::$table . '.baseline',
            HomeworkTaskModel::$table . '.is_complete(complete)',
            HomeworkTaskModel::$table . '.note_ids',
            HomeworkTaskModel::$table . '.homework_audio',
            HomeworkTaskModel::$table . '.audio_duration',
            TeacherModel::$table . '.name(teacher_name)'
        ], $where);
        return $result;
    }
}
