<?php
/**
 * Created by PhpStorm.
 * User: mncu
 * Date: 2019/4/16
 * Time: 16:30
 */
namespace App\Models;

use App\Libs\MysqlDB;


class HomeworkCompleteModel extends Model
{
    public static $table = "homework_complete";


    /**
     * @param int $playRecordId 练琴记录ID
     * @param array $homeworks 作业记录
     */
    public static function finishHomework($playRecordId, $homeworks){
        $data = [];
        foreach ($homeworks as $homework) {
            array_push($data, [
                'homework_id' => $homework['id'],
                'play_record_id' => $playRecordId,
                'task_id' => $homework['task_id'],
                'create_time' => time()
            ]);
        }
        if(empty($data)){
            return;
        }
        $db = MysqlDB::getDB();
        $db->insert(self::$table, $data);
    }

    public static function getPlayRecordIdByTaskId($taskId){
        $db = MysqlDB::getDB();
        $result = $db->select(
            HomeworkCompleteModel::$table,
            ['play_record_id'],
            ['task_id' => $taskId]
        );
        return $result;
    }
}