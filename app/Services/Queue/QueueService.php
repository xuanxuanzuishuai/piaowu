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
use App\Libs\Util;
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
     * @param int $deferMax
     * @return bool
     * 消息规则推送
     */
    public static function messageRulePushMessage($sendArr, $deferMax = 0)
    {
        try {
            $topic = new PushMessageTopic();
            $pushTime = time();
            $deferMax = $deferMax ?: self::getDeferMax(count($sendArr));
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
     * @param $uuidList
     * @param $logId
     * @param $employeeId
     * @throws Exception
     * 推送用户列表放入队列
     */
    public static function pushWxUuid($uuidList, $logId, $employeeId)
    {
        $topic = new PushMessageTopic();
        $uuidListGroup = array_chunk($uuidList, 5000);
        foreach ($uuidListGroup as $value) {
            $msgBody = [
                'uuidList'   => $value,
                'logId'      => $logId,
                'employeeId' => $employeeId
            ];
            $topic->pushWxUuid($msgBody)->publish();
        }
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
            $deferMax = intval($count/2);

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

    /**
     * 更新积分兑换红包领取进度
     * @param $data
     * @return bool
     */
    public static function awardPointsRedPackUpdateSpeed($data) {
        try {
            $topic = new UserPointsExchangeRedPackTopic();
            $topic->updateRedPackSpeed($data)->publish(5);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 周周有礼/月月有奖消息推送
     * @param $data [open_id => [data]]
     * @param $type
     * @return bool
     */
    public static function weekAndMonthRewardMessage($data, $type)
    {
        if (empty($data) || empty($type)) {
            return false;
        }
        $ruleId = 0;
        switch ($type) {
            case PushMessageTopic::EVENT_WEEK_REWARD_MON:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_mon_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_TUE:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_tue_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_WED:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_wed_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_THUR:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_thur_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_FRI:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_fri_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_SAT:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_sat_rule_id');
                break;

            case PushMessageTopic::EVENT_WEEK_REWARD_SUN:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'week_reward_sun_rule_id');
                break;

            case PushMessageTopic::EVENT_MONTH_REWARD_MON:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'month_reward_mon_rule_id');
                break;

            case PushMessageTopic::EVENT_MONTH_REWARD_WED:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'month_reward_wed_rule_id');
                break;

            case PushMessageTopic::EVENT_MONTH_REWARD_FRI:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'month_reward_fri_rule_id');
                break;

            case PushMessageTopic::EVENT_MONTH_REWARD_SUN:
                $ruleId = DictConstants::get(DictConstants::MESSAGE_RULE, 'month_reward_sun_rule_id');
                break;

            default:
                break;
        }
        if (empty($ruleId)) {
            return false;
        }

        foreach ($data as $k => $v) {
            //过滤不活跃无法收到消息的openId
            if (!PushMessageService::checkLastActiveTime($k)) {
                unset($data[$k]);
            }
        }

        MessageService::sendMessage($data, $ruleId, null, Util::TIMESTAMP_1H);
    }
    /**
     * 截图审核通过发奖
     * @param $data
     * @return bool
     */
    public static function addUserPosterAward($data)
    {
        try {
            $topic = new UserPointsExchangeRedPackTopic();
            $topic->addUserPosterAward($data)->publish(5);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * ocr审核海报
     * @param $data
     * @return bool
     */
    public static function checkPoster($data): bool
    {
        try {
            $topic = new CheckPosterSyncTopic();
            $topic->checkPoster($data)->publish(5);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data ?? []);
            return false;
        }
        return true;
    }

    /**
     * 保存 QR Ticket
     * @param $data
     * @return bool
     */
    public static function saveTicket($data)
    {
        try {
            (new SaveTicketTopic())->sendTicket($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 生成 QR Ticket
     * @param $data
     * @return bool
     */
    public static function genTicket($data)
    {
        try {
            (new SaveTicketTopic())->genTicket($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }
}