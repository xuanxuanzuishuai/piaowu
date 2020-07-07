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

class CreditService
{
    //积分活动类型
    const CREDIT_TASK = 6;
    //每日任务
    const EVERY_DAY_ACTIVITY = 'every_day_activity';
    //每日任务完成情况
    const EVERY_DAY_ACTIVITY_COMPLETE_STATUS = 'every_day_activity_complete_status';
    //签到活动
    const SIGN_IN_TASKS = 'sign_in_tasks';
    //练琴活动
    const PLAY_PIANO_TASKS = 'play_piano_tasks';
    //双手评测
    const BOTH_HAND_EVALUATE = 'both_hand_evaluate';
    //分享成绩
    const SHARE_GRADE = 'share_grade';

    /**
     * @param $date
     * 设置每日活动模板
     */
    public static function createEveryDayTask($date)
    {
        $redis = RedisDB::getConn();
        $field = self::EVERY_DAY_ACTIVITY . '_' . $date;
        $erp = new Erp();
        $relateTasks = $erp->eventTaskList(0, self::CREDIT_TASK)['data'];
        //需要区分签到任务还是练琴任务
        //签到相关的task
        $signTaskArr = [];
        $signInTasks = self::getSignInTask();
        //每日练琴相关的task
        $playPianoTaskArr = [];
        $playPianoTasks = self::getPlayPianoTask();
        //双手评测相关的task
        $bothHandEvaluateArr = [];
        $bothHandEvaluate = self::getBothHandTask();
        //分享评测成绩
        $shareEvaluateGradesArr = [];
        $shareEvaluateGrades = self::getShareGradeTask();
        if (!empty($relateTasks)) {
            foreach ($relateTasks as $va) {
                if (!empty($va['tasks'])) {
                    foreach ($va['tasks'] as $v) {
                        if (in_array($v['id'], $signInTasks)) {
                            $signTaskArr[] = $v;
                        }
                        if (in_array($v['id'], $playPianoTasks)) {
                            $playPianoTaskArr[] = $v;
                        }
                        if (in_array($v['id'], $bothHandEvaluate)) {
                            $bothHandEvaluateArr[] = $v;
                        }
                        if (in_array($v['id'], $shareEvaluateGrades)) {
                            $shareEvaluateGradesArr[] = $v;
                        }
                    }
                }
            }
        }
        $redis->hset(self::SIGN_IN_TASKS, $field, json_encode($signTaskArr));
        $redis->hset(self::PLAY_PIANO_TASKS, $field, json_encode($playPianoTaskArr));
        $redis->hset(self::BOTH_HAND_EVALUATE, $field, json_encode($bothHandEvaluateArr));
        $redis->hset(self::SHARE_GRADE, $field, json_encode($shareEvaluateGradesArr));
        //设置过期
        $endTime = strtotime($date) + 86410 - time();
        $redis->setex($field, $endTime, 1);
    }

    /**
     * @return array|mixed|null
     * 签到相关的task
     */
    private static function getSignInTask()
    {
       return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'every_day_sign_in_one_day_task_id',
            'every_day_sign_in_two_day_task_id',
            'every_day_sign_in_three_day_task_id',
            'every_day_sign_in_four_day_task_id',
            'every_day_sign_in_five_day_task_id',
            'every_day_sign_in_six_day_task_id',
            'every_day_sign_in_seven_day_task_id',
            'every_day_sign_in_than_seven_day_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 每日练琴相关的task
     */
    private static function getPlayPianoTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG,[
            'play_piano_thirty_m_task_id',
            'play_piano_forty_m_task_id',
            'play_piano_sixty_m_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 双手评测相关task
     */
    private static function getBothHandTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'both_hands_evaluate_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 分享评测相关task
     */
    private static function getShareGradeTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'share_evaluate_grade_task_id'
        ]);
    }

    /**
     * @param null $certainKey
     * @return mixed
     * 得到当前的活动模板
     */
    public static function getActivityTemplate($certainKey = NULL)
    {
        $field = self::EVERY_DAY_ACTIVITY . '_' . date('Y-m-d');
        $redis = RedisDB::getConn();
        //极限情况如果没有在此再生成一次
        if (empty($redis->get($field))) {
            self::createEveryDayTask(date('Y-m-d'));
        }
        if (!empty($certainKey)) {
            return json_decode($redis->hget($certainKey, $field), true);
        }
        $activityArr['sign_in_tasks'] = json_decode($redis->hget(self::SIGN_IN_TASKS, $field), true);
        $activityArr['play_piano_tasks'] = json_decode($redis->hget(self::PLAY_PIANO_TASKS, $field), true);
        $activityArr['both_hand_evaluate'] = json_decode($redis->hget(self::BOTH_HAND_EVALUATE, $field), true);
        $activityArr['share_grade'] = json_decode($redis->hget(self::SHARE_GRADE, $field), true);
        return $activityArr;
    }

    /**
     * @param $type
     * @param $activityData
     * @return mixed
     * @throws RunTimeException
     * 根据用户参加的活动给用户奖励
     */
    public static function setUserCompleteTask($type, $activityData)
    {
        if (!in_array($type, [self::SIGN_IN_TASKS, self::PLAY_PIANO_TASKS, self::BOTH_HAND_EVALUATE, self::SHARE_GRADE])) {
            throw new RunTimeException(['not_support_type']);
        }
        $action = self::getActivityClass($type) . 'Action';

        return self::$action($activityData);
    }

    private static function getActivityClass($type)
    {
        switch ($type) {
            case self::SIGN_IN_TASKS;
                return 'signInTasks';
            case self::PLAY_PIANO_TASKS;
                return 'playPianoTasks';
            case self::BOTH_HAND_EVALUATE:
                return 'bothHandEvaluate';
            case self::SHARE_GRADE:
                return 'shareGrade';
        }
    }

    /**
     * @param $data
     * @return array|void
     * 处理签到
     */
    public static function signInTasksAction($data)
    {
        //前置检查对当前任务的完成状态
        $completeStatus = self::getUserCompleteStatus($data['student_id'], self::SIGN_IN_TASKS);
        //每个任务只需要完成一次
        if (!empty($completeStatus)) {
            foreach ($completeStatus as $va) {
                if ($va['is_complete']) {
                    SimpleLogger::info(self::SIGN_IN_TASKS . ' has complete', ['activity' => $va]);
                    return;
                }
            }
        }

        $erp = new Erp();
        $activityTemplate = self::getActivityTemplate(self::SIGN_IN_TASKS);
        $shouldGetTaskId = 0;
        //防止需要完成的任务天数大小错乱，引入临时数
        $tmpDay = 0;
        $limitCount = 0;
        $hasAchieveTaskId = [];
        foreach ($activityTemplate as $v) {
            $continueDays = json_decode($v['condition'], true)['continue_days'];
            if ($continueDays >= $tmpDay) {
                $tmpDay = $continueDays;
            }
            if ($data['continue_days'] >= $continueDays && $continueDays >= $tmpDay) {
                $shouldGetTaskId = $v['id'];
                $limitCount = $v['every_day_count'];
            }
        }
        $hasAchieveTaskId[] = $shouldGetTaskId;
        $erp->updateTask($data['uuid'], $shouldGetTaskId, ErpReferralService::EVENT_TASK_STATUS_COMPLETE);

        //更新用户的完成情况
        self::updateUserCompleteStatus($data['student_id'], self::SIGN_IN_TASKS, $limitCount, $shouldGetTaskId);
        return $hasAchieveTaskId;
    }

    /**
     * @param $data
     * @return array
     * 处理每日练琴
     */
    public static function playPianoTasksAction($data)
    {
        //对当前任务的完成状态
        $completeStatus = self::getUserCompleteStatus($data['student_id'], self::PLAY_PIANO_TASKS);
        $erp = new Erp();
        $activityTemplate = self::getActivityTemplate(self::PLAY_PIANO_TASKS);
        $hasAchieveTaskId = [];
        foreach ($activityTemplate as $v) {
            //多个需要不同完成
            if (!empty($completeStatus[$v['id']]['is_complete'])) {
                continue;
            }
            $condition = json_decode($v['condition'], true);
            if ($data['play_duration'] >= $condition['play_duration']) {
                $erp->updateTask($data['uuid'], $v['id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
                self::updateUserCompleteStatus($data['student_id'], self::PLAY_PIANO_TASKS, $v['every_day_count'], $v['id']);
                $hasAchieveTaskId[] = $v['id'];
            }
        }
        return $hasAchieveTaskId;
    }

    /**
     * @param $data
     * @return array|void
     * 处理每日完成1次双手全曲评测
     */
    public static function bothHandEvaluateAction($data)
    {
        //对当前任务的完成状态
        $completeStatus = self::getUserCompleteStatus($data['student_id'], self::BOTH_HAND_EVALUATE);
        //只要有一个完成就算完成
        if (!empty($completeStatus)) {
            foreach ($completeStatus as $va) {
                if ($va['is_complete']) {
                    SimpleLogger::info(self::BOTH_HAND_EVALUATE . ' has complete', ['activity' => $va]);
                    return;
                }
            }
        }
        $erp = new Erp();
        $activityTemplate = self::getActivityTemplate(self::BOTH_HAND_EVALUATE);
        $hasAchieveTaskId = [];
        foreach ($activityTemplate as $v) {
            $erp->updateTask($data['uuid'], $v['id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            self::updateUserCompleteStatus($data['student_id'], self::BOTH_HAND_EVALUATE, $v['every_day_count'], $v['id']);
            $hasAchieveTaskId[] = $v['id'];
        }
        return $hasAchieveTaskId;
    }

    /**
     * @param $data
     * @return array|void
     * 处理每日分享评测成绩
     */
    public static function shareGradeAction($data)
    {
        //对当前任务的完成情况
        $completeStatus = self::getUserCompleteStatus($data['student_id'], self::SHARE_GRADE);
        //只要有一个完成就算完成
        if (!empty($completeStatus)) {
            foreach ($completeStatus as $va) {
                if ($va['is_complete']) {
                    SimpleLogger::info(self::SHARE_GRADE . ' has complete', ['activity' => $va]);
                    return;
                }
            }
        }
        $erp = new Erp();
        $activityTemplate = self::getActivityTemplate(self::SHARE_GRADE);
        $hasAchieveTaskId = [];
        foreach ($activityTemplate as $v) {
            $erp->updateTask($data['uuid'], $v['id'], ErpReferralService::EVENT_TASK_STATUS_COMPLETE);
            self::updateUserCompleteStatus($data['student_id'], self::SHARE_GRADE, $v['every_day_count'], $v['id']);
            $hasAchieveTaskId[] = $v['id'];
        }
        return $hasAchieveTaskId;
    }

    /**
     * @param $studentId
     * @param $type
     * @param $limitCount
     * @param $taskId
     * 更新用户的完成情况
     */
    public static function updateUserCompleteStatus($studentId, $type, $limitCount, $taskId)
    {
        $redis = RedisDB::getConn();
        $date = date('Y-m-d');
        $field = self::EVERY_DAY_ACTIVITY_COMPLETE_STATUS . '_' . $studentId . '_' . $date;

        $data = $redis->hget($type, $field);
        if (empty($data)) {
            $isComplete = $limitCount <= 1;
            $completeCount = 1;
            $dataArr = [$taskId => ['is_complete' => $isComplete, 'complete_count' => $completeCount, 'limit_count' => $limitCount]];
        } else {
            $dataArr = json_decode($data, true);
            $dataArr[$taskId]['complete_count'] = isset($dataArr[$taskId]['complete_count']) ? $dataArr[$taskId]['complete_count'] + 1 : 1;
            $dataArr[$taskId]['is_complete'] = $dataArr[$taskId]['complete_count'] >= $limitCount;
            $dataArr[$taskId]['limit_count'] = $limitCount;
        }
        $redis->hset($type, $field, json_encode($dataArr));
        //设置过期
        $endTime = strtotime($date) + 86410 - time();
        $redis->setex($field, $endTime, 1);
    }

    /**
     * @param $studentId
     * @param null $type
     * @return mixed
     * 得到用户的对每日任务的完成情况
     */
    public static function getUserCompleteStatus($studentId, $type = NULL)
    {
        $redis = RedisDB::getConn();
        $field = self::EVERY_DAY_ACTIVITY_COMPLETE_STATUS . '_' . $studentId . '_' . date('Y-m-d');
        if (!empty($type)) {
            return json_decode($redis->hget($type, $field), true);
        }
        $activityArr['sign_in_tasks'] = json_decode($redis->hget(self::SIGN_IN_TASKS, $field), true);
        $activityArr['play_piano_tasks'] = json_decode($redis->hget(self::PLAY_PIANO_TASKS, $field), true);
        $activityArr['both_hand_evaluate'] = json_decode($redis->hget(self::BOTH_HAND_EVALUATE, $field), true);
        $activityArr['share_grade'] = json_decode($redis->hget(self::SHARE_GRADE, $field), true);
        return $activityArr;
    }

}