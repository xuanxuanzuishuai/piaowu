<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/7
 * Time: 3:29 PM
 */

namespace App\Services;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\AIPlayRecordCHModel;
use App\Models\CategoryV1Model;
use App\Models\EmployeeModel;
use App\Models\GoodsV1Model;
use App\Models\StudentMedalModel;
use App\Models\StudentModel;
use App\Models\UserWeixinModel;
use App\Models\WeChatAwardCashDealModel;

class TermSprintService
{
    //活动类型
    const TERM_SPRINT_TYPE = 8;

    //学期task公用key
    const TERM_SPRINT_DAILY_KEY = 'medal_daily_key';

    //学期冲刺event配置field
    const TERM_SPRINT_EVENT_SETTING = 'term_sprint_event_setting';
    //学期冲刺task配置field
    const TERM_SPRINT_TASK_SETTING = 'term_sprint_task_setting';
    //冲刺人数
    const TERM_SPRINT_NUM = 'term_sprint_num';
    //瓜分助跑金
    const PARTITION_TERM_SPRINT_CASH_NUM = 'partition_term_sprint_cash_num';

    /**
     * @param $date
     * 设置奖章模板
     */
    public static function createEveryDayTask($date)
    {
        $redis = RedisDB::getConn();
        $erp = new Erp();
        $relateTasks = $erp->eventTaskList(0, self::TERM_SPRINT_TYPE)['data'];
        //设置过期
        $allTaskArr = [];
        $endTime = strtotime($date) + 172800 - time();
        array_map(function ($item) use($redis, $date, $endTime, &$allTaskArr) {
            $allTaskArr = array_merge($item['tasks'], $allTaskArr);
            $key = self::getTermSprintRelateKey($item['id'], $date);
            $redis->hset($key, self::TERM_SPRINT_EVENT_SETTING, $item['settings']);
            $redis->hset($key, self::TERM_SPRINT_TASK_SETTING, json_encode($item['tasks']));
            return $redis->expire($key, $endTime);
        }, $relateTasks);
        //缓存奖章在弹窗的时候需要的信息
        $medalBaseInfo = array_column(GoodsV1Model::getMedalInfo(), NULL,'medal_id');
        array_map(function ($info) use($redis, $medalBaseInfo, $endTime, $date) {
            $awardInfo = json_decode($info['award'], true)['awards'];
            if (!empty($awardInfo)) {
                foreach ($awardInfo as $award) {
                    if ($award['type'] == CategoryV1Model::MEDAL_AWARD_TYPE) {
                        $medalKey = MedalService::getMedalInfoKey($award['course_id'], $date);
                        $medalBase = $medalBaseInfo[$award['course_id']];
                        $medalBase['task_desc'] = $info['name'];
                        $redis->hset($medalKey,MedalService::MEDAL_INFO_KEY, json_encode($medalBase));
                        return $redis->expire($medalKey, $endTime);
                    }
                }
            }
        },$allTaskArr);
    }

    public static function getTermSprintRelateKey($eventId, $date = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        return self::TERM_SPRINT_DAILY_KEY . '_' . $date . '_' . $eventId;
    }

    /**
     * @param $studentId
     * @return array
     * 学期冲刺首页
     */
    public static function getTermSprintRelateTask($studentId)
    {
        $student = StudentModel::getRecord(['id' => $studentId]);
        list($eventSetting, $relateTasks, $validData) = self::basePlayInfo($studentId);
        $date = date('Ymd');
        //当日有效计数
        $nowValidNum = $validData[$date] ?? 0;
        //总计有效计数
        $totalValidNum = array_sum($validData);
        //每日计数上限
        $everyDayCount = $eventSetting['every_day_valid_minutes'];
        //多少宝贝正在冲刺
        $totalBabySprintNum = RedisDB::getConn()->get(self::TERM_SPRINT_NUM) ?: DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'init_sprint_num');
        //多少宝贝参与了瓜分助跑金
        $totalBabyPartitionNum = NULL;
        if (time() >= strtotime($eventSetting['show_partition_time'])) {
            //做缓存
            $redisPartitionNum = RedisDB::getConn()->get(self::PARTITION_TERM_SPRINT_CASH_NUM);
            if (empty($redisPartitionNum)) {
                $taskId = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'cash_award_task_id');
                $num = count((new Erp())->getTaskCompleteUser(['task_id' => $taskId])['data']['records']);
                $totalBabyPartitionNum = $num * 5;
                RedisDB::getConn()->setex(self::PARTITION_TERM_SPRINT_CASH_NUM, 5, $totalBabyPartitionNum);
            } else {
                $totalBabyPartitionNum = $redisPartitionNum;
            }
        }
        $needInfo = [];
        if (!empty($relateTasks)) {
            $records = (new Erp())->getUserTaskRelateInfo(['uuid' => $student['uuid'], 'task_id' => implode(',', array_column($relateTasks, 'id'))])['data']['records'] ?? [];
            $relateInfo = array_column($records, 'status', 'event_task_id');
            foreach ($relateTasks as $task) {
                $award = json_decode($task['award'], true)['awards'][0];
                $needInfo[] = [
                    'task_id' => $task['id'],
                    'valid_num' => json_decode($task['condition'], true)['valid_num'],
                    'award_type' => $award['type'],
                    'award_amount' => $award['amount'],
                    'has_draw' => (isset($relateInfo[$task['id']]) && $relateInfo[$task['id']] == ErpReferralService::EVENT_TASK_STATUS_COMPLETE)
                ];
            }
        }

        return [
            'base_info' => $needInfo,
            'now_valid_num' => $nowValidNum,
            'total_valid_num' => $totalValidNum,
            'every_day_count' => $everyDayCount,
            'total_baby_sprint_num' => $totalBabySprintNum,
            'total_baby_partition_num' => $totalBabyPartitionNum
        ];
    }

    /**
     * @param $studentId
     * @return array
     * 基础配置练琴数据
     */
    private static function basePlayInfo($studentId)
    {
        $relateMedalEvent = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, [
            'term_sprint_event'
        ]);
        $relateMedalEventId = reset($relateMedalEvent);
        $eventSetting = json_decode(RedisDB::getConn()->hget(self::getTermSprintRelateKey($relateMedalEventId), self::TERM_SPRINT_EVENT_SETTING), true);
        $relateTasks = json_decode(RedisDB::getConn()->hget(self::getTermSprintRelateKey($relateMedalEventId), self::TERM_SPRINT_TASK_SETTING), true);
        $playData = AIPlayRecordCHModel::getStudentSumByDate($studentId, strtotime($eventSetting['start_time']), strtotime($eventSetting['end_time']) + Util::TIMESTAMP_ONEDAY);
        //根据每日统计上限计算用户有效数据
        $validData = [];
        array_map(function ($v) use(&$validData, $eventSetting) {
            $minutes = floor($v['sum_duration'] / 60);
            $validData[$v['play_date']] = $minutes >= $eventSetting['every_day_valid_minutes'] ? $eventSetting['every_day_valid_minutes'] : $minutes;
        }, $playData);
        return [$eventSetting, $relateTasks, $validData];
    }

    /**
     * @param $studentId
     * @param $taskId
     * @throws RunTimeException
     * 发送学期冲刺奖励
     */
    public static function drawAward($studentId, $taskId)
    {
        $student = StudentModel::getRecord(['id' => $studentId]);
        list($eventSetting, $relateTasks, $validData) = self::basePlayInfo($studentId);
        //是否已过时间
        $time = time();
        if ($time < strtotime($eventSetting['start_time'])) {
            throw new RunTimeException(['term_sprint_not_start']);
        }
        if ($time >= (strtotime($eventSetting['end_time']) + Util::TIMESTAMP_ONEDAY)) {
            throw new RunTimeException(['term_sprint_has_end']);
        }
        //总计有效计数
        $totalValidNum = array_sum($validData);
        $taskInfo = array_column($relateTasks, NULL, 'id')[$taskId];
        if (empty($taskInfo)) {
            throw new RunTimeException(['not_relate_task']);
        }
        $needNum = json_decode($taskInfo['condition'], true)['valid_num'];
        $award = json_decode($taskInfo['award'], true)['awards'][0];
        if ($needNum > $totalValidNum) {
            throw new RunTimeException(['not_reach_valid_num']);
        }
        if ($award['type'] == ErpReferralService::AWARD_TYPE_CASH) {
            //校验用户是否已经绑定微信
            $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($studentId,
                UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
                WeChatService::USER_TYPE_STUDENT,
                UserWeixinModel::BUSI_TYPE_STUDENT_SERVER);
            if (empty($studentWeChatInfo)) {
                return ['not_weixin' => 'not_bound_we_chat'];
            }
        }
        $erp = new Erp();
        //积分
        $erp->updateTask($student['uuid'], $taskId, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
    }

    /**
     * 更新每日冲刺人数
     */
    public static function updateCashAttendNum()
    {
        $startTime = strtotime(date("Y-m-d",strtotime("-1 day")));
        $num = AIPlayRecordCHModel::getStudentPlayNum($startTime, $startTime + Util::TIMESTAMP_ONEDAY)['play_user_num'];
        //现在有多少宝贝正在冲刺
        $totalBabySprintNum = RedisDB::getConn()->get(self::TERM_SPRINT_NUM) ?: DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'init_sprint_num');
        //更新冲刺人数
        RedisDB::getConn()->set(self::TERM_SPRINT_NUM, $num + $totalBabySprintNum);
    }

    /**
     * 活动结束发红包
     */
    public static function giveUserTermSprintAward()
    {
        //红包
        $cashTaskId = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'cash_award_task_id');
        $cashInfo = (new Erp())->getTaskCompleteUser(['task_id' => $cashTaskId])['data']['records'];
        $allTaskAwardIdArr = array_column($cashInfo, 'task_award_id');
        SimpleLogger::info('new term sprint red pack award id', ['task_award_id' => $allTaskAwardIdArr]);
        //已经发放中或者发放成功的不再重试
        $hasAchieveWeChatDeal = WeChatAwardCashDealModel::getRecords(['user_event_task_award_id' => $allTaskAwardIdArr, 'status' => [ErpReferralService::AWARD_STATUS_GIVEN, ErpReferralService::AWARD_STATUS_GIVE_ING]], 'user_event_task_award_id') ?: [];
        $count = count($cashInfo);
        $amount = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'total_cash_amount');
        $getAmount = floor(($amount / $count) * 100);
        SimpleLogger::info('new term sprint red pack amount', ['amount' => $amount, 'get_amount' => $getAmount]);
        foreach ($cashInfo as $value) {
            if (in_array($value['task_award_id'], $hasAchieveWeChatDeal)) {
                continue;
            }
            CashGrantService::cashGiveOut($value['uuid'], $value['task_award_id'], $getAmount, EmployeeModel::SYSTEM_EMPLOYEE_ID, WeChatAwardCashDealModel::TERM_SPRINT_PIC_WORD);
        }
        //奖章
        $medalTaskId = DictConstants::get(DictConstants::TERM_SPRINT_CONFIG, 'medal_award_task_id');
        $medalInfo = (new Erp())->getTaskCompleteUser(['task_id' => $medalTaskId])['data']['records'];
        if (!empty($medalInfo)) {
            foreach ($medalInfo as $value) {
                $student = StudentModel::getRecord(['uuid' => $value['uuid']]);
                //用户已经获取的奖章
                $studentMedalInfo = array_column(StudentMedalModel::getRecords(['student_id' => $student['id']]), 'medal_id');
                if ($value['award_type'] == CategoryV1Model::MEDAL_AWARD_TYPE && !in_array($value['course_id'], $studentMedalInfo)) {
                    $medalInfo = MedalService::getMedalIdInfo($value['course_id']);
                    MedalService::updateStudentMedalCategoryInfo($student['id'], $medalInfo['category_id']);
                    //记录用户所得奖章的详细信息
                    StudentMedalModel::insertRecord(
                        [
                            'student_id' => $student['id'],
                            'medal_id' => $value['course_id'],
                            'medal_category_id' => $medalInfo['category_id'],
                            'task_id' => $value['event_task_id'],
                            'create_time' => time(),
                            'report_log_id' => 0,
                            'is_show' => StudentMedalModel::IS_ACTIVE_SHOW
                        ]);
                }
            }
        }
    }
}