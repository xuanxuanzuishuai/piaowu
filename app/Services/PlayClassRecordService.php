<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/12/26
 * Time: 2:13 PM
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Models\PlayClassPartModel;
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

    /**
     * 开始上课
     * @param $sessionId
     * @param $studentId
     * @param $lessonId
     * @param $startTime
     * @return int
     */
    public static function classStart($sessionId, $studentId, $lessonId, $startTime)
    {
        $now = time();

        $recordData = [
            'student_id' => $studentId,
            'lesson_id' => $lessonId,
            'start_time' => $startTime,
            'duration' => 0, // 异步更新
            'create_time' => $now,
            'class_session_id' => $sessionId,
            'update_time' => 0, // 异步更新
            'best_record_id' => 0, // 异步更新
        ];

        $recordId =  PlayClassRecordModel::insertRecord($recordData);

        if (empty($recordId)) {
            return 0;
        }

        StudentModel::updateRecord($studentId, ['last_play_time' => $now], false);

        return $recordId;
    }


    /**
     * 更新上课进度
     * @param $sessionId
     * @param $duration
     * @param $recordId
     * @param $recordDuration
     * @return bool
     */
    public static function classProgress($sessionId, $duration, $recordId, $recordDuration)
    {
        $record = PlayClassRecordModel::getBySessionId($sessionId);
        if (empty($record)) {
            return 0;
        }

        PlayClassPartModel::insertRecord([
            'class_session_id' => $sessionId,
            'record_id' => $recordId,
            'duration' => $recordDuration,
            'create_time' => time()
        ], false);

        if ($duration <= $record['duration']) {
            return 0;
        }

        $cnt = PlayClassRecordModel::updateRecord($record['id'], [
            'duration' => $duration
        ], false);

        return $cnt > 0 ? 1 : 0;
    }

    /**
     * 上课结束
     * @param $sessionId
     * @param $bestRecordId
     * @param $duration
     * @return bool
     */
    public static function classEnd($sessionId, $bestRecordId, $duration)
    {
        $record = PlayClassRecordModel::getBySessionId($sessionId);
        if (empty($record)) {
            return 0;
        }

        $update = [
            'best_record_id' => $bestRecordId ?? 0
        ];
        if (!empty($duration)) {
            $update['duration'] = $duration;
        }
        $cnt = PlayClassRecordModel::updateRecord($record['id'], $update, false);

        return $cnt > 0 ? 1 : 0;
    }
}