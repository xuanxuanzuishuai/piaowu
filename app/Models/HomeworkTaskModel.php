<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;

use App\Libs\MysqlDB;


class HomeworkTaskModel extends Model
{
    public static $table = "homework_task";

    /** 是否已经完成  */
    const TYPE_COMPLETE = 1;                 // 已经完成
    const TYPE_UNCOMPLETE = 0;               // 未完成

    public static function completeTask($tasks){
        $taskIds = array_column($tasks, 'task_id');
        if(empty($taskIds)){
            return;
        }
        MysqlDB::getDB()->update(
            self::$table,
            ['is_complete' => self::TYPE_COMPLETE],
            ['id' => $taskIds]
        );
    }

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

    public static function getRecentCollectionIds($teacher_id, $page, $limit) {
        $start = ($page - 1) * $limit;

        $query = "select distinct " . self::$table . ".collection_id from " . self::$table . " inner join "
            . HomeworkModel::$table . " on " . self::$table . ".homework_id = " . HomeworkModel::$table . ".id" .
            " where " . HomeworkModel::$table . ".teacher_id=" . $teacher_id . " order by " . HomeworkModel::$table .
            ".created_time desc limit " . $start . ", " . $limit;
        return MysqlDB::getDB()->queryAll($query);
    }

    public static function getRecentLessonIds($teacher_id, $page, $limit) {
        $start = ($page - 1) * $limit;

        $query = "select distinct " . self::$table . ".lesson_id from " . self::$table . " inner join "
            . HomeworkModel::$table . " on " . self::$table . ".homework_id = " . HomeworkModel::$table . ".id" .
            " where " . HomeworkModel::$table . ".teacher_id=" . $teacher_id . " order by " . HomeworkModel::$table .
            ".created_time desc limit " . $start . ", " . $limit;
        return MysqlDB::getDB()->queryAll($query);
    }

}
