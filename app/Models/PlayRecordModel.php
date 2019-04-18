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


    public static function getPlayRecordByLessonId($lessonId, $recordType=null){
        $db = MysqlDB::getDB();
        $where = ['lesson_id' => $lessonId];
        if (!empty($recordType)){
            $where['record_type'] = $recordType;
        }
        $result = $db->select(PlayRecordModel::$table, '*', $where);
        return $result;
    }
}