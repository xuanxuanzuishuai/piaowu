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


class HomeworkTaskModel extends Model
{
    public static $table = "homework_task";

    /** 是否已经完成  */
    const TYPE_COMPLETE = 1;                 // 已经完成
    const TYPE_UNCOMPLETE = 0;               // 未完成


    /**
     * 标记task已经达成
     * @param $tasks
     */
    public static function completeTask($tasks){
        $taskIds = [];
        foreach ($tasks as $task){
            $complete = (int)$task['complete'];
            if ($complete == self::TYPE_UNCOMPLETE){
                array_push($taskIds, $task['task_id']);
            }
        }
        if(empty($taskIds)){
            return;
        }
        MysqlDB::getDB()->update(
            self::$table,
            ['is_complete' => self::TYPE_COMPLETE],
            ['id' => $taskIds]
        );
    }

    /**
     * 创建task
     * @param $homework_id
     * @param $lesson_id
     * @param $lesson_name
     * @param $collection_id
     * @param $collection_name
     * @param $baseline
     * @return int|mixed|null|string
     */
    public static function createHomeworkTask($homework_id, $lesson_id, $lesson_name, $collection_id, $collection_name, $baseline) {
        return MysqlDB::getDB()->insertGetID(self::$table, [
            'homework_id' => $homework_id,
            'lesson_id' => $lesson_id,
            'lesson_name' => $lesson_name,
            'collection_id' => $collection_id,
            'collection_name' => $collection_name,
            'baseline' => $baseline,
        ]);
    }

    public static function getRecentCollectionIds($teacher_id, $page, $limit, $student_id=null) {
        $start = ($page - 1) * $limit;

        $query = "select distinct " . self::$table . ".collection_id from " . self::$table . " inner join "
            . HomeworkModel::$table . " on " . self::$table . ".homework_id = " . HomeworkModel::$table . ".id" .
            " where " . HomeworkModel::$table . ".teacher_id=" . $teacher_id;

        if (!empty($student_id)){
            $query = $query . " and " . HomeworkModel::$table .".student_id=" . $student_id;
        }
        $query = $query . " order by " . HomeworkModel::$table .
            ".created_time desc limit " . $start . ", " . $limit;
        $result = MysqlDB::getDB()->queryAll($query);
        $result = array_column($result, "collection_id");
        return $result;
    }

    public static function getRecentLessonIds($teacher_id, $page, $limit, $student_id=null) {
        $start = ($page - 1) * $limit;

        $query = "select distinct " . self::$table . ".lesson_id from " . self::$table . " inner join "
            . HomeworkModel::$table . " on " . self::$table . ".homework_id = " . HomeworkModel::$table . ".id" .
            " where " . HomeworkModel::$table . ".teacher_id=" . $teacher_id;
        if (!empty($student_id)){
            $query = $query . " and " . HomeworkModel::$table .".student_id=" . $student_id;
        }
        $query = $query . " order by " . HomeworkModel::$table .
            ".created_time desc limit " . $start . ", " . $limit;
        $result = MysqlDB::getDB()->queryAll($query);
        $result = array_column($result, "lesson_id");
        return $result;
    }

}
