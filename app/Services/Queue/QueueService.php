<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/12/15
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

use App\Libs\SimpleLogger;
use Exception;
class QueueService
{

    private static function getDeferMax($count)
    {
        return $count >= 4 ? $count : 4; //红包发送大概一秒一个，目前处理直接定义
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public static function sendRedPack($data)
    {
        try {
            $deferMax = self::getDeferMax(count($data));
            foreach ($data as $award) {
                (new RedPackTopic())->sendRedPack(['award_id' => $award['id']])->publish(rand(2, $deferMax));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public static function updateRedPack($data)
    {
        try {
            $deferMax = self::getDeferMax(count($data));
            foreach ($data as $award) {
                (new RedPackTopic())->updateRedPack(['award_id' => $award['id']])->publish(rand(0, $deferMax));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * @param $sendArr
     * @return bool
     * 消息规则推送
     */
    public static function messageRulePushMessage($sendArr)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();
            $deferMax = self::getDeferMax(count($sendArr));
            array_map(function ($i) use($topic, $deferMax, $pushTime){
                $deferMax += $i['delay_time'];
                $i['push_wx_time'] = $pushTime;
                $topic->pushRuleWx($i)->publish(rand(0, $deferMax));
            }, $sendArr);

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * 给微信用户推送活动消息
     * @param $students
     * @param $guideWord
     * @param $shareWord
     * @param $posterUrl
     * @param $activityId
     * @param $employeeId
     * @param $activityType
     * @return bool
     */
    public static function pushWX($students, $guideWord, $shareWord, $posterUrl, $activityId, $employeeId, $activityType)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();

            $count = count($students);
            if ($count > 5000) { // 超过5000条，半小时内发送完
                $deferMax = 1800;
            } elseif ($count > 1000) { // 超过1000条，10分钟内发送完
                $deferMax = 600;
            } else { // 默认2分钟内发送完
                $deferMax = 120;
            }

            foreach ($students as $student) {
                $msgBody = [
                    'student_id'    => $student['user_id'],
                    'open_id'       => $student['open_id'],
                    'guide_word'    => $guideWord,
                    'share_word'    => $shareWord,
                    'poster_url'    => $posterUrl,
                    'push_wx_time'  => $pushTime,
                    'activity_id'   => $activityId,
                    'employee_id'   => $employeeId,
                    'activity_type' => $activityType
                ];

                $topic->pushWX($msgBody)->publish(rand(0, $deferMax));
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }
    /**
     * @param $sendArr
     * @param $logId
     * @param $employeeId
     * @param $activityType
     * @return bool
     * 手动push消息
     */
    public static function manualPushMessage($sendArr, $logId, $employeeId, $activityType)
    {
        try {
            $topic    = new PushMessageTopic();
            $pushTime = time();
            $deferMax = self::getDeferMax(count($sendArr));
            array_map(function ($i) use ($topic, $deferMax, $pushTime, $logId, $employeeId, $activityType) {
                $deferMax += $i['delay_time'];
                $i['push_wx_time']  = $pushTime;
                $i['log_id']        = $logId;
                $i['activity_type'] = $activityType;
                $i['employee_id']   = $employeeId;
                $topic->pushManualRuleWx($i)->publish(rand(0, $deferMax));
            }, $sendArr);

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }
}