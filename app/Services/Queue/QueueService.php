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
        return $count; //红包发送大概一秒一个，目前处理直接定义
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
                (new RedPack())->sendRedPack(['award_id' => $award['id']])->publish(rand(0, $deferMax));
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
                (new RedPack())->updateRedPack(['award_id' => $award['user_event_task_award_id']])->publish(rand(0, $deferMax));
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
}