<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/12/15
 * Time: 11:29 AM
 */

namespace App\Services\Queue;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\EmployeeModel;
use App\Services\MessageService;
use App\Services\PushMessageService;
use App\Services\StudentService;
use App\Services\WeekWhiteListService;
use Exception;

class QueueService
{
    const FROM_DSS = 10;
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
                // 用户最后活跃时间距离当前已过50个小时不发送
                if (!PushMessageService::checkLastActiveTime($student['open_id'])) {
                    continue;
                }
                // 检查用户是否是周周领奖白名单
                try {
                    if (WeekWhiteListService::checkStudentIsWhite($student['open_id'], 0)) {
                        throw new RunTimeException(['invalid_data']);
                    }
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
                } catch (RunTimeException $e) {
                    SimpleLogger::info("pushWX", ['msg' => 'checkStudentIsWhite', $e->getAppErrorData(), $students, $guideWord, $shareWord, $posterUrl, $activityId, $employeeId, $activityType]);
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
            // 获取延时时间， 默认1-30秒随机数， 如果指定延时时间则使用指定的时间
            $deferSecond = rand(1, 30);
            if (!empty($data['defer_second'])) {
                $deferSecond = intval($data['defer_second']);
            }
            SimpleLogger::info("addUserPosterAward", [$data]);
            $topic = new UserPointsExchangeRedPackTopic();
            $topic->addUserPosterAward($data)->publish($deferSecond);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 真人 - 截图审核通过发奖
     * 队列延时：默认1-30秒随机数， 如果指定延时时间则使用指定的时间
     * @param $data
     * @return bool
     */
    public static function addRealUserPosterAward($data): bool
    {
        try {
            if (!is_array($data) || empty($data)) {
                return false;
            }
            $topic = new RealReferralTopic();
            // 获取延时时间， 默认1-30秒随机数， 如果指定延时时间则使用指定的时间
            $deferSecond = rand(1, 30);
            foreach ($data as $dv) {
                if (!empty($dv['defer_second'])) {
                    $deferSecond = intval($dv['defer_second']);
                }
                $topic->realSendPosterAward($dv)->publish($deferSecond);
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), ['addRealUserPosterAward', $data]);
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

    /**
     * 手动批量推送消息 - 周周有奖后台提醒发送功能
     * @param int $logId message_manual_push_log表主键
     * @param array $uuidArr 用户的uuid
     * @param int $employeeId 操作人id
     * @return bool
     */
    public static function manualBatchPushRuleWxByUuid($logId, $uuidArr, $employeeId)
    {
        try {
            (new PushMessageTopic())->pushWX([
                'uuids' => $uuidArr,
                'log_id' => $logId,
                'employee_id' => $employeeId,
            ], PushMessageTopic::EVENT_PUSH_BATCH_MANUAL_RULE_WX)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $uuidArr);
            return false;
        }
        return true;
    }

    /**
     * 截图审核-消息
     * @param $data
     * @return bool
     */
    public static function sharePosterAwardMessage($data)
    {
        try {
            (new PushMessageTopic())->sharePosterAwardMessage($data)->publish(5);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 修改学员姓名及推送收藏
     *
     * @param array $data
     * @param int $delay
     * @return bool
     */
    public static function updateStudentNameAndCollect(array $data, int $delay = 0)
    {
        try {
            (new StudentOpernTopic())->pushStudentInfo($data)->publish(rand(1, $delay));
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 发送待生成小程序码id到队列
     * @param $data
     * @param $deferTime
     * @param $secondNum
     * @return bool
     */
    public static function sendWaitCreateMiniAppQrId($data, $deferTime = 0, $secondNum = 1)
    {
        try {
            if (empty($data)) {
                return false;
            }
            $secondNum = $secondNum > 0 ? $secondNum : 1;
            $topic = new WechatTopic();
            foreach ($data as $i => $item) {
                // 计算延时  - 每秒50， 队列延时1秒
                $defer = $i + $deferTime;
                $topic->waitCreateMiniAppQrId($item)->publish(intval($defer / $secondNum));
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 智能 - 启动批量生成小程序码id的任务
     * @param $data
     * @param $deferTime
     * @return bool
     */
    public static function startCreateMiniAppId($data = [], $deferTime = 0)
    {
        try {
            if (empty($data)) {
                $data['time'] = time();
            }
            $data['app_id']      = Constants::SMART_APP_ID;
            $data['busies_type'] = Constants::SMART_MINI_BUSI_TYPE;
            (new WechatTopic())->startCreateMiniAppId($data)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 启动生成待使用二维码标识
     * @param $data
     * @param $deferTime
     * @return bool
     */
    public static function startCreateWaitUseQrId($data = [], $deferTime = 0)
    {
        try {
            if (empty($data)) {
                $data['time'] = time();
            }
            (new WechatTopic())->startCreateWaitUseQrId($data)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 真人智能-自动激活
     * @param $data
     */
    public static function autoActivate($data)
    {
        try {
            $topic = new AutoActivate();
            $topic->checkAutoActivate($data)->publish(5);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data ?? []);
        }
    }

    /**
     * 更新达标期数
     * @param $data
     */
    public static function editQualified($data)
    {
        try {
            $topic = new GrantAwardTopic();
            $topic->editQualified($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data ?? []);
        }
    }

    /**
     * 报名
     * @param $data
     */
    public static function signCountingActivityUp($data){
        try {
            $topic = new GrantAwardTopic();
            $topic->signUp($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data ?? []);
        }
    }

    /**
     * 抽奖活动发送奖品
     * @param $data
     * @return bool
     */
    public static function lotteryGrantAward($data){
        try {
            $topic = new GrantAwardTopic();
            $topic->lotteryGrantAward($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data ?? []);
        }
        return true;
    }

    /**
     * 赠送时长
     * @param $uuid
     * @param $applyType
     * @param $goodsNum
     * @param $channel
     * @param int $operatorId
     * @param null $msg
     * @return bool
     */
    public static function giftDuration($uuid, $applyType, $goodsNum, $channel, $operatorId = 0, $msg = null)
    {
        try {
            $topic = new GiftCoursesTopic(null, self::FROM_DSS);
            if (!is_array($uuid)) {
                $uuid = [$uuid];
            }

            if (!empty($operatorId)) {
                $operatorName = DssEmployeeModel::getById($operatorId)['name'];
            } else {
                $operatorId = EmployeeModel::SYSTEM_EMPLOYEE_ID;
                $operatorName = EmployeeModel::SYSTEM_EMPLOYEE_NAME;
            }
            foreach ($uuid as $value) {
                $msgBody = [
                    'uuid' => $value,
                    'apply_type' => $applyType,
                    'goods_num' => $goodsNum,
                    'channel' => $channel,
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'msg' => $msg
                ];
                $topic->giftDuration($msgBody)->publish();
            }

        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $msgBody ?? []);
            return false;
        }
        return true;
    }

    /**
     * 任务发放奖励
     *
     * @param array $data
     * @param int $delay
     * @return bool
     */
    public static function countingAward(array $data, int $delay = 0)
    {
        try {
            (new GrantAwardTopic())->countingAward($data)->publish($delay);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }



    /**
     * 金叶子发放微信消息
     * @param array $data
     * @return bool
     */
    public static function sendGoldLeafWxMessage(array $data)
    {
        try {
            $topic = new PushMessageTopic();
            $topic->sendGoldLeafWxMessage($data)->publish(0);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 白名单发放红包
     * @param $data
     * @return bool
     */
    public static function weekWhiteGrandLeaf($data){
        try {
            $topic = new WeekActivityTopic();
            $rand = rand(0,3600);
            $topic->weekWhiteGrandLeaf($data)->publish($rand);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 获取发放红包状态
     * @param $data
     * @return bool
     */
    public static function getWeekWhiteSendRedPkgStatus($data){
        try {
            $topic = new WeekActivityTopic();
            $rand = rand(0,240);
            $topic->getWeekWhiteSendRedPkgStatus($data)->publish($rand);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 周周领奖任务之计数任务奖励，实物发货物流信息同步
     * @param array $data
     * @param int $delay
     * @return bool
     */
    public static function syncCountingAwardLogistics(array $data, int $delay = 0)
    {
        try {
            (new GrantAwardTopic())->countingSyncAwardLogistics($data)->publish($delay);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }

    /**
     * 给用户发送消息
     * 备注：最好在$data里面加入log_sign参数用于追踪定位日志
     * @param $appId
     * @param $userId
     * @param $wechatConfigId
     * @param $data
     * @return bool
     */
    public static function sendUserWxMsg($appId, $userId, $wechatConfigId, $data): bool
    {
        try {
            (new PushMessageTopic())->pushWX([
                'app_id' => $appId,
                'user_id' => $userId,
                'wechat_config_id' => $wechatConfigId,
                'replace_params' => $data['replace_params'] ?? [],
            ], PushMessageTopic::EVENT_SEND_USER_MSG)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$appId, $userId, $wechatConfigId, $data]);
            return false;
        }
        return true;
    }

    /**
     * @param $data
     * @return bool
     * 投放小程序注册
     */
    public static function formRegister($data)
    {
        try {
            (new AdTrackTopic())->formRegister($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }


    /***********************************************************************/
    /******************** 真人业务的消息队列 start ****************************/
    /***********************************************************************/

    /**
     * 真人 - 启动批量生成小程序码id的任务
     * @param $data
     * @param $deferTime
     * @return bool
     */
    public static function realStartCreateMiniAppId($data = [], $deferTime = 0)
    {
        try {
            if (empty($data)) {
                $data['time'] = time();
            }
            $data['app_id'] = Constants::REAL_APP_ID;
            $data['busies_type'] = Constants::REAL_MINI_BUSI_TYPE;
            (new WechatTopic())->startCreateMiniAppId($data)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }


    /**
     * 智能年卡小程序 - 启动批量生成小程序码id的任务
     * @param $data
     * @param $deferTime
     * @return bool
     */
    public static function dssYearCardStartCreateMiniAppId($data = [], $deferTime = 0)
    {
        try {
            if (empty($data)) {
                $data['time'] = time();
            }
            $data['app_id'] = Constants::SMART_APP_ID;
            $data['busies_type'] = DssUserWeiXinModel::BUSI_TYPE_YEAR_CARD_MINAPP;
            (new WechatTopic())->startCreateMiniAppId($data)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }


    /**
     * 真人 - 用户积分(魔法石)入账
     * @param $uuid
     * @param $amount
     * @param $sourceType
     * @param $remark
     * @param $createTime
     * @param $batchId
     * @param $deferTime
     * @return bool
     */
    public static function erpMagicStoneNormalCredited($uuid, $amount, $sourceType, $remark, $createTime, $batchId = '', $deferTime = 0)
    {
        try {
            $data = [
                'app_id' => Constants::REAL_APP_ID,
                'student_uuid' => $uuid,
                'sub_type' => Constants::ERP_ACCOUNT_NAME_MAGIC,
                'source_type' => $sourceType,
                'remark' => $remark,
                'num' => $amount,
                'create_time' => $createTime,
                'expire_time' => $createTime,
            ];
            // batch_id 如果不需要不会带这个参数
            !empty($batchId) && $data['batch_id'] = $batchId;
            (new ErpStudentAccountTopic())->erpNormalCredited($data)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 真人 - 微信消息 - 审核截图发送奖励
     * @param $data
     * @return bool
     */
    public static function realSendPosterAwardMessage($data)
    {
        try {
            if (!is_array($data) || empty($data)) {
                return false;
            }
            $topicObj = new RealReferralTopic();
            foreach ($data as $dk => $dv) {
                $topicObj->realSendPosterAwardMessage($dv)->publish(5);
            }
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }


    /**
     *
     * @param $data
     * @return bool
     */
    public static function studentRegistered($data)
    {
        try {
            $topic = new DouStoreTopic();
            $topic->studentRegistered($data)->publish();
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /***********************************************************************/
    /******************** 真人业务的消息队列 end ******************************/
    /***********************************************************************/

    /**
     * 清晨landing页订单地址校验延迟信息
     * @param $data
     * @return bool
     */
    public static function qcLandingOrderAddress($data)
    {
        try {
            $topic = new PushMessageTopic();
            $topic->qcLandingOrderAddress($data)->publish(300);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), $data);
            return false;
        }
        return true;
    }

    /**
     * 清晨 - 投递消息
     * @param $eventType
     * @param $data
     * @param int $deferTime
     * @return bool
     */
    public static function morningPushMsg($eventType, $data, $deferTime = 0)
    {
        try {
            if (!is_array($data) || empty($data)) {
                return false;
            }
            $topicObj = new MorningReferralTopic();
            $topicObj->nsqDataSet($data,$eventType)->publish($deferTime);
        } catch (Exception $e) {
            SimpleLogger::error($e->getMessage(), [$data]);
            return false;
        }
        return true;
    }
}
