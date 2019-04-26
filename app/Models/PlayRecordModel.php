<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/2/22
 * Time: 2:36 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;

class PlayRecordModel extends Model
{
    public static $table = 'play_record';

    /** 练琴类型 */
    const TYPE_DYNAMIC = 0;         // 动态曲谱
    const TYPE_AI = 1;              // ai曲谱

    /** 课上课下 */
    const TYPE_ON_CLASS = 0;       // 课上练琴
    const TYPE_OFF_CLASS = 1;      // 课下练琴


    public static function getPlayRecordByLessonId($lessonId, $studentId,
                                                   $recordType=null,
                                                   $createdTime=null,
                                                   $endTime=null){
        $db = MysqlDB::getDB();
        $where = ['lesson_id' => $lessonId, 'student_id' => $studentId];
        if (!empty($recordType)){
            $where['lesson_type'] = $recordType;
        }
        if (!empty($createdTime) && !empty($endTime)){
            $where['created_time[>=]'] = $createdTime;
            $where['end_time[<=]'] = $endTime;

        }
        $result = $db->select(PlayRecordModel::$table, '*', $where);
        return $result;
    }

    /** 获取学生课程报告
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return array|null
     */
    public static function getPlayRecordReport($student_id, $start_time, $end_time){
        $sql = "select 
                    lesson_id, 
                    lesson_type, 
                    count(lesson_sub_id) as sub_count,
                    sum(duration) as duration ,
                    sum(if(lesson_type=0 and lesson_sub_id is null, 1, 0)) as dmc,
                    sum(if(lesson_type=1 and lesson_sub_id is null, 1, 0)) as ai, 
                    max(if(lesson_type=0 and lesson_sub_id is null, score, 0)) as max_dmc, 
                    max(if(lesson_type=1 and lesson_sub_id is null, score, 0)) as max_ai
            from play_record 
            where 
                student_id = :student_id and 
                created_time >= :start_time and 
                created_time <= :end_time
            group by 
              lesson_id, lesson_type";
        $map = [":student_id" => $student_id, ":start_time" => $start_time, ":end_time" => $end_time];
        $db = MysqlDB::getDB();
        $result = $db->queryAll($sql, $map);
        return $result;
    }

    /**
     * @param $lesson_id
     * @param $student_id
     * @param $start_time
     * @param $end_time
     * @return mixed
     */
    public static function getWonderfulAIRecordId($lesson_id, $student_id, $start_time, $end_time) {
        $db = MysqlDB::getDB();
        $result = $db->get(self::$table, ["ai_record_id", "score"], [
            "lesson_id" => $lesson_id,
            "student_id" => $student_id,
            "created_time[>=]" => $start_time,
            "created_time[<]" => $end_time,
            "lesson_type" => self::TYPE_AI,
            "ORDER" => [
                "score" => "DESC"
            ]
        ]);
        return $result;
    }

}
