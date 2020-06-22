<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2020/06/22
 * Time: 下午6:18
 */

namespace App\Models;


use App\Libs\MysqlDB;

class VoiceCallLogModel extends Model
{
    public static $table = "app_voice_call_log";

    const RECEIVE_STUDENT = 1;
    const RECEIVE_TEACHER = 2;

    const CALL_TYPE_URGE = 1; //开始上课前提醒
    const CALL_TYPE_STUDENT_LATE = 2; //学生迟到外呼
    const CALL_TYPE_TEACHER_LATE = 3; //老师迟到外呼
    const VOICE_TYPE_PURCHASE_EXPERIENCE_CLASS = 4; //提醒用户体验课购买成功


    public static function getRecordByUniqueId($uniqueId){

        $record = MysqlDB::getDB()->get(self::$table, "*", ['unique_id' => $uniqueId]);
        return $record;
    }

    /**
     * 保存通话日志
     * @param $params
     * @return mixed
     */
    public static function saveCallLog($params)
    {
        $db = MysqlDB::getDB();
        return $db->insertGetID(self::$table, $params);

    }

    public static function updateById($id, $update)
    {
        $result = self::updateRecord($id, $update);
        return ($result && $result > 0);
    }
}