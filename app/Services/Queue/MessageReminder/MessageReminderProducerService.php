<?php

namespace App\Services\Queue\MessageReminder;

use App\Libs\SimpleLogger;
use Exception;

class MessageReminderProducerService
{
    /**
     * 由addEventTaskAward()方法发送的奖励，触发消息提醒
     * @param array $pointsAwardIds
     * @return bool
     */
    public static function addEventTaskAwardMessageReminderProducer(array $pointsAwardIds): bool
    {
        try {
            if (empty($pointsAwardIds)) {
                return false;
            }
            $nsqObj = new MessageReminderTopic();
            $nsqObj->nsqDataSet(["award_ids" => $pointsAwardIds],
                $nsqObj::EVENT_TYPE_TASK_AWARD_MESSAGE_REMINDER)->publish(5);//此处延时是考虑到数据库主从延时
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), []);
            return false;
        }
        return true;
    }
}