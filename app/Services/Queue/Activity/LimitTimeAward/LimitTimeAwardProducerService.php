<?php

namespace App\Services\Queue\Activity\LimitTimeAward;

use App\Libs\SimpleLogger;
use App\Services\AutoCheckPicture;
use Exception;

class LimitTimeAwardProducerService
{
    /**
     * 自动审核消息生产者
     * @param int $sharePosterId
     * @param int $userId
     * @param int $defTime
     * @return bool
     */
    public static function autoCheckProducer(int $sharePosterId, int $userId, int $defTime): bool
    {
        try {
            $nsqObj = new LimitTimeAwardTopic();
            $nsqObj->nsqDataSet(
                [
                    "record_id" => $sharePosterId,
                    "user_id"         => $userId,
                    "activity_type"   => AutoCheckPicture::SHARE_POSTER_TYPE_LIMIT_TIME_AWARD,
                ],
                $nsqObj::EVENT_TYPE_SHARE_POSTER_AUTO_CHECK)
                ->publish($defTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), []);
            return false;
        }
        return true;
    }

    /**
     * 发奖生产者
     * @param int $sharePosterRecordId
     * @param array $params
     * @return bool
     */
    public static function sendAwardProducer(int $sharePosterRecordId, array $params = []): bool
    {
        try {
            $defTime = $params['def_time'] ?? 0;
            $nsqObj = new LimitTimeAwardTopic();
            $nsqObj->nsqDataSet(
                [
                    "record_id" => $sharePosterRecordId,
                ],
                $nsqObj::EVENT_TYPE_SEND_AWARD)
                ->publish($defTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), []);
            return false;
        }
        return true;
    }
}