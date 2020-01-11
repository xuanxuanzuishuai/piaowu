<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/26
 * Time: 2:13 PM
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Models\PlayClassRecordModel;
use App\Models\StudentModel;

class PlayClassRecordService
{
    /**
     * @param $studentId
     * @param $playData
     * @return int|mixed|null|string
     * @throws RunTimeException
     */
    public static function addRecord($studentId, $playData)
    {
        $now = time();

        $recordData = [
            'student_id' => $studentId,
            'lesson_id' => $playData['lesson_id'],
            'start_time' => $playData['start_time'],
            'duration' => $playData['duration'],
            'create_time' => $now,
            'class_session_id' => $playData['class_session_id'],
            'update_time' => 0, // 异步更新
            'best_record_id' => 0, // 异步更新
        ];

        $recordId =  PlayClassRecordModel::insertRecord($recordData);

        if (empty($recordId)) {
            throw new RunTimeException(['insert_failure']);
        }

        StudentModel::updateRecord($studentId, ['last_play_time' => $now], false);

        return $recordId;
    }

    /**
     * 处理更新记录队列消息
     * @param $message
     * @return bool
     */
    public static function handleClassUpdate($message)
    {
        // TODO: bug update_time 一定会更新, updateRecord 总返回 true
        $sessionId = $message['session_id'];
        $data = [
            'update_time' => time(),
            'best_record_id' => $message['eval_id'],
        ];
        return self::updateRecord($sessionId, $data);
    }

    /**
     * 更新记录
     * @param $classSessionId
     * @param $data
     * @return bool
     */
    public static function updateRecord($classSessionId, $data)
    {
        $cnt = PlayClassRecordModel::batchUpdateRecord($data, ['class_session_id' => $classSessionId], false);
        return $cnt > 0;
    }
}