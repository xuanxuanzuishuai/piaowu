<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/1/6
 * Time: 11:02 AM
 */

namespace App\Services;


use App\Libs\SimpleLogger;
use App\Models\PlayClassRecordMessageModel;

class PlayClassRecordMessageService
{
    public static function handleMessage($message)
    {
        switch ($message['event_type']) {
            case 'class_start':
                $ret = self::start($message['msg_body']);
                break;
            case 'class_progress':
                $ret = self::progress($message['msg_body']);
                break;
            case 'class_end':
                $ret = self::end($message['msg_body']);
                break;
            case 'class_update':
                $ret = self::save($message);
                break;
            case 'play_start':
                $ret = self::playStart($message['msg_body']);
                break;
            case 'play_heart_beat':
                $ret = self::heartBeat($message['msg_body']);
                break;
            case 'play_end':
                $ret = self::playEnd($message['msg_body']);
                break;

            default:
                SimpleLogger::info("[PlayClassRecordMessageService handleMessage] unknown message", [
                    'message' => $message
                ]);
                $ret = 0;
        }
        return $ret;
    }

    public static function start($message)
    {
        $student = StudentService::getByUuid($message['uuid']);
        if (empty($student)) {
            return 0;
        }

        return PlayClassRecordService::classStart($message['session_id'],
            $student['id'],
            $message['lesson_id'],
            $message['start_time']);
    }

    public static function progress($message)
    {
        return PlayClassRecordService::classProgress(
            $message['session_id'],
            $message['duration'],
            $message['eval_id'],
            $message['eval_duration']
        );
    }

    public static function end($message)
    {
        return PlayClassRecordService::classEnd($message['session_id'],
            $message['eval_id'],
            $message['duration']);
    }

    public static function save($message)
    {
        $data = [
            'create_time' => time(),
            'body' => json_encode($message)
        ];
        $id = PlayClassRecordMessageModel::insertRecord($data, false);
        return $id;
    }

    public static function playStart($message)
    {
        $student = StudentService::getByUuid($message['uuid']);
        if (empty($student)) {
            return 0;
        }
        $result = AIPlayRecordService::start($student['id'], $message);
        if (empty($result)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__ . " play start error ", [
                'message' => $message
            ]);
        }
        return $result;
    }

    public static function heartBeat($message)
    {
        $result = AIPlayRecordService::heartBeat($message);
        if (empty($result)) {
            SimpleLogger::error( __FILE__ . ":" . __LINE__ . " play heart beat error", [
                'message' => $message
            ]);
        }
        return $result;
    }

    public static function playEnd($message)
    {
        $student = StudentService::getByUuid($message['uuid']);
        if (empty($student)) {
            return 0;
        }

        $result = AIPlayRecordService::end($student['id'], $message);
        if (empty($result)) {
            SimpleLogger::error(__FILE__ . ":" . __LINE__ . " play end error", [
                'message' => $message
            ]);
        }
        return $result;
    }
}