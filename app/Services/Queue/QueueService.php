<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/12/15
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Services\MessageService;
use App\Services\PushMessageService;
use Exception;

class QueueService
{

    const FROM_OP = 19;

    private static function getDeferMax($count)
    {
        return $count >= 4 ? $count : 4; //红包发送大概一秒一个，目前处理直接定义
    }

    /**
     * 发送红包
     * @param $data
     * @return bool
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
     * 发放时长奖励
     * @param $data
     * @return bool
     */
    public static function sendDuration($data)
    {
        try {
            $markList = [];
            foreach ($data as $award) {
                $times = $markList[$award['user_id']] ?? 0;
                $delay = $times * 3;
                (new DurationTopic())->sendDuration(['award_id' => $award['id']])->publish($delay);
                $markList[$award['user_id']] = $times + 1;
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
                if (PushMessageService::checkLastActiveTime($student['open_id'])) {
                    $topic->pushWX($msgBody)->publish(rand(0, $deferMax));
                }
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
            $deferMax = self::getDeferMax(count($sendArr));
            $now      = time();
            foreach ($sendArr as $item) {
                $deferMax += $item['delay_time'];
                $item['push_wx_time']  = $now;
                $item['log_id']        = $logId;
                $item['activity_type'] = $activityType;
                $item['employee_id']   = $employeeId;
                $topic->pushManualRuleWx($item)->publish(rand(0, $deferMax));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * 班级消息推送
     * @param $data [open_id => [data]]
     * @param $type
     * @return bool
     */
    public static function classMessage($data, $type)
    {
        if (empty($data) || empty($type)) {
            return false;
        }
        $ruleId = 0;
        switch ($type) {
            case PushMessageTopic::EVENT_BEFORE_CLASS_ONE:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'before_class_one_day_rule_id');
                break;

            case PushMessageTopic::EVENT_BEFORE_CLASS_TWO:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'before_class_two_day_rule_id');
                break;

            case PushMessageTopic::EVENT_AFTER_CLASS_ONE:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'after_class_one_day_rule_id');
                break;

            case PushMessageTopic::EVENT_START_CLASS:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'start_class_day_rule_id');
                break;

            case PushMessageTopic::EVENT_START_CLASS_SEVEN:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'start_class_seven_day_rule_id');
                break;
            default:
                break;
        }
        if (empty($ruleId)) {
            return false;
        }
        MessageService::sendMessage($data, $ruleId);
        return true;
    }

    /**
     * 未练琴消息推送
     * @param $data [open_id => [data]]
     * @param $day
     * @return bool
     */
    public static function noPlayMessage($data, $day)
    {
        $config = DictConstants::get(DictConstants::MESSAGE_RULE, 'no_play_day_rule_config');
        if (empty($config)) {
            return false;
        }
        $config = json_decode($config, true);
        $ruleId = $config[$day] ?? 0;
        if (empty($ruleId)) {
            return false;
        }
        MessageService::sendMessage($data, $ruleId);
        return true;
    }

    /**
     * 每月活动消息
     * @param $openIds
     * @return bool
     */
    public static function monthlyEvent($openIds)
    {
        try {
            $topic = new PushMessageTopic();
            foreach ($openIds as $openId) {
                if (PushMessageService::checkLastActiveTime($openId)) {
                    $topic->monthlyPush($openId)->publish(rand(0, 3600));
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * 智能陪练push
     * @param $syncData
     * @return bool
     */
    public static function aiplPush($syncData)
    {
        try {
            $topic = new PushMessageTopic();

            $count = $syncData['count'];
            if ($count > 500) { // 超过500条，半小时内发送完
                $deferMax = 1800;
            } elseif ($count > 100) { // 超过100条，10分钟内发送完
                $deferMax = 600;
            } elseif ($count > 30) { // 2分钟内发送完
                $deferMax = 120;
            } else {
                $deferMax = 0;
            }
            $topic->aiplPush($syncData)->publish(rand(0, $deferMax));
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $syncData);
            return false;
        }
        return true;
    }

    /**
     * 打卡海报审核消息
     * @param $day
     * @param $status
     * @param $openId
     * @param $appId
     * @return bool
     */
    public static function checkinPosterMessage($day, $status, $openId, $appId)
    {
        try {
            $topic = new PushMessageTopic();
            $data = [
                'day'     => $day,
                'status'  => $status,
                'open_id' => $openId,
                'app_id'  => $appId,
            ];
            $topic->checkinMessage($data)->publish(rand(0, 10));
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$day, $status, $openId, $appId]);
            return false;
        }
        return true;
    }

    /**
     * 给助教推送学员页面动态短信
     * @param $data
     * @param int $delay
     * @return bool
     */
    public static function sendAssistantSms($data, $delay = 900)
    {
        try {
            $topic = new PushMessageTopic();
            $topic->webPageMessage($data)->publish($delay);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 给助教发送短信埋点
     * @param $data
     * @param int $delay
     * @return bool
     */
    public static function sendAssistantSmsBi($data, $delay = 0)
    {
        try {
            $sensorTopic = new SaBpDataTopic();
            $sensorTopic->sendAssistantSms($data)->publish($delay);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 更新用户标签
     * @param $openIds
     * @param int $delay
     * @return bool
     */
    public static function dailyUpdateUserMenuTag($openIds, $delay = 1800)
    {
        try {
            $topic = new WechatTopic();
            foreach ($openIds as $openId) {
                $topic->updateUserTag($openId)->publish(rand(1, $delay));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$openIds]);
            return false;
        }
        return true;
    }


}