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
}