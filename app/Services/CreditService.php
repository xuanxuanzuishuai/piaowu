<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2020/7/7
 * Time: 3:29 PM
 */

namespace App\Services;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\EventModel;
use App\Models\EventTaskModel;
use App\Models\PointActivityRecordModel;

class CreditService
{
    //积分活动类型
    const CREDIT_TASK = 6;
    //每日任务活动
    const EVERY_DAY_ACTIVITY_NAME = "每日任务活动";
    //活动任务群体是时长有效用户(1所有2时长有效)
    const SUB_REMAIN_DURATION_VALID = 2;
    //每日任务
    const EVERY_DAY_ACTIVITY = 'every_day_activity';
    //每日任务完成情况
    const EVERY_DAY_ACTIVITY_COMPLETE_STATUS = 'every_day_activity_complete_status';
    //每日任务上报情况
    const EVERY_DAY_ACTIVITY_REPORT_STATUS = 'every_day_activity_report_status';
    //并发锁
    const CONCURRENCY_LOCK_KEY = 'concurrency_lock_key';
    //签到活动
    const SIGN_IN_TASKS = 'sign_in_tasks';
    //练琴活动
    const PLAY_PIANO_TASKS = 'play_piano_tasks';
    //双手评测
    const BOTH_HAND_EVALUATE = 'both_hand_evaluate';
    //分享成绩
    const SHARE_GRADE = 'share_grade';
    //完成音基题
    const MUSIC_BASIC_QUESTION = 'music_basic_question';
    //示范视频
    const EXAMPLE_VIDEO = 'example_video';
    //互动课堂本日首次上课
    const ATTEND_CLASS = 'attend_class';
    //浏览重难点
    const VIEW_DIFFICULT_SPOT = 'view_difficult_spot';
    //识谱，提升
    const KNOW_CHART_PROMOTION = 'know_chart_promotion';
    //奖励类型
    const CREDIT_AWARD_TYPE = 3;
    //所有积分互动和奖励执行方法对应关系
    const ALL_ACTIVITY_RELATE_CLASS = [
        self::SIGN_IN_TASKS => 'signInTasks',
        self::PLAY_PIANO_TASKS => 'playPianoTasks',
        self::BOTH_HAND_EVALUATE => 'bothHandEvaluate',
        self::SHARE_GRADE => 'shareGrade',
        self::MUSIC_BASIC_QUESTION => 'musicBasicQuestion',
        self::EXAMPLE_VIDEO => 'exampleVideo',
        self::ATTEND_CLASS => 'attendClass',
        self::VIEW_DIFFICULT_SPOT => 'viewDifficultSpot',
        self::KNOW_CHART_PROMOTION => 'knowChartPromotion'
    ];

    private static function getAllReportType()
    {
        return array_keys(self::ALL_ACTIVITY_RELATE_CLASS);
    }

    /**
     * @param $type
     * @return string
     * 积分活动和对应的奖励动作的对应关系
     */
    private static function getActivityClass($type)
    {
        return self::ALL_ACTIVITY_RELATE_CLASS[$type];
    }

    /**
     * @param $date
     * 设置每日活动模板
     */
    public static function createEveryDayTask($date)
    {
        $redis = RedisDB::getConn();
        $field = self::EVERY_DAY_ACTIVITY;
        $erp = new Erp();
        $relateTasks = $erp->eventTaskList(0, self::CREDIT_TASK)['data'];
        //需要区分签到任务还是练琴任务
        //签到相关的task
        $signInTasks = self::getSignInTask();
        //每日练琴相关的task
        $playPianoTasks = self::getPlayPianoTask();
        //双手评测相关的task
        $bothHandEvaluate = self::getBothHandTask();
        //分享评测成绩
        $shareEvaluateGrades = self::getShareGradeTask();
        //完成音基题
        $musicBasicQuestion = self::getMusicBasicQuestionTask();
        //示范视频
        $exampleVideo = self::getExampleVideoTask();
        //互动课堂本日首次上课
        $attendClass = self::getAttendClassTask();
        //浏览重难点
        $viewDifficultSpot = self::getViewDifficultSpotTask();
        //识谱，提升
        $knowChartPromotion = self::getKnowChartPromotionTask();
        $allActivityArr = [];
        if (!empty($relateTasks)) {
            foreach ($relateTasks as $va) {
                if (!empty($va['tasks'])) {
                    foreach ($va['tasks'] as $v) {
                        if (in_array($v['id'], $signInTasks)) {
                            $allActivityArr[self::SIGN_IN_TASKS][] = $v;
                        }
                        if (in_array($v['id'], $playPianoTasks)) {
                            $allActivityArr[self::PLAY_PIANO_TASKS][] = $v;
                        }
                        if (in_array($v['id'], $bothHandEvaluate)) {
                            $allActivityArr[self::BOTH_HAND_EVALUATE][] = $v;
                        }
                        if (in_array($v['id'], $shareEvaluateGrades)) {
                            $allActivityArr[self::SHARE_GRADE][] = $v;
                        }
                        if (in_array($v['id'], $musicBasicQuestion)) {
                            $allActivityArr[self::MUSIC_BASIC_QUESTION][] = $v;
                        }
                        if (in_array($v['id'], $exampleVideo)) {
                            $allActivityArr[self::EXAMPLE_VIDEO][] = $v;
                        }
                        if (in_array($v['id'], $attendClass)) {
                            $allActivityArr[self::ATTEND_CLASS][] = $v;
                        }
                        if (in_array($v['id'], $viewDifficultSpot)) {
                            $allActivityArr[self::VIEW_DIFFICULT_SPOT][] = $v;
                        }
                        if (in_array($v['id'], $knowChartPromotion)) {
                            $allActivityArr[self::KNOW_CHART_PROMOTION][] = $v;
                        }
                    }
                }
            }
        }
        //设置过期
        $endTime = strtotime($date) + 172800 - time();
        array_map(function ($item) use($redis, $field, $date, $allActivityArr, $endTime) {
            $key = self::getActivityTaskRelateKey($date, $item);
            $redis->hset($key, $field, json_encode($allActivityArr[$item]));
            return $redis->expire($key, $endTime);
        }, self::getAllReportType());
    }

    /**
     * @param null $date
     * @param null $type
     * @return string|string[]
     * 活动模板相关key
     */
    private static function getActivityTaskRelateKey($date = NULL, $type = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        if (!empty($type)) {
            return $type . '_' . $date;
        }
        return array_map(function ($item) use ($date){
            return $item . '_' . $date;
        }, self::getAllReportType());
    }

    /**
     * @param null $date
     * @param null $type
     * @param $studentId
     * @return string|string[]
     * 活动完成状态相关key
     */
    private static function getActivityTaskFinishRelateKey($studentId, $date = NULL, $type = NULL)
    {
        empty($date) && $date = date('Y-m-d');
        if (!empty($type)) {
            return $type . '_' . $date . '_' . $studentId;
        }
        return array_map(function ($item) use ($date, $studentId){
            return $item . '_' . $date . '_' . $studentId;
        }, self::getAllReportType());
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
     * @return array|mixed|null
     * 音基题相关的task
     */
    private static function getMusicBasicQuestionTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'music_basic_question_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 示范视频相关的task
     */
    private static function getExampleVideoTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'example_video_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 互动课堂本日首次上课相关的task
     */
    private static function getAttendClassTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'attend_class_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 浏览重难点相关的task
     */
    private static function getViewDifficultSpotTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'view_difficult_spot_task_id'
        ]);
    }

    /**
     * @return array|mixed|null
     * 识谱，提升相关的task
     */
    private static function getKnowChartPromotionTask()
    {
        return DictConstants::get(DictConstants::CREDIT_ACTIVITY_CONFIG, [
            'know_chart_promotion_task_id'
        ]);
    }

    /**
     * @param null $certainKey
     * @return mixed
     * 得到当前的活动模板
     */
    public static function getActivityTemplate($certainKey = NULL)
    {
        $field = self::EVERY_DAY_ACTIVITY;
        $redis = RedisDB::getConn();
        if (!empty($certainKey)) {
            return json_decode($redis->hget(self::getActivityTaskRelateKey(NULL, $certainKey), $field), true);
        }
        $activityArr = [];
        array_map(function ($item) use (&$activityArr, $redis, $field){
            $activityArr[$item] = json_decode($redis->hget(self::getActivityTaskRelateKey(NULL, $item), $field), true);
        }, self::getAllReportType());
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
        if (!in_array($type, self::getAllReportType())) {
            throw new RunTimeException(['not_support_type']);
        }
        //并发处理
        $cacheKey = self::CONCURRENCY_LOCK_KEY . '_' . date('Y-m-d') . '_' . $activityData['student_id'] . '_' . $type;
        $redis = RedisDB::getConn();
        $result = NULL;
        if ($redis->setnx($cacheKey, 1)) {
            $redis->expire($cacheKey, 5);
            //记录上报情况 
            self::recordActivityReport($type, $activityData);
            //并发处理
            //检测是否满足可上报的基础条件(每日完成x次行为，奖励x音符，每天最多奖励x次),检测x次行为是否达到
            if (self::judgeUserReportData($type, $activityData)) {
                $action = self::getActivityClass($type) . 'Action';
                $creditResult = self::$action($activityData);
                $medalResult = MedalService::dealMedalGrantRelate($type, $activityData);
                $result = empty($creditResult) ? $medalResult : $creditResult + $medalResult;
            } else {
                SimpleLogger::info('not reach report basic require', ['type' => $type, 'activity_data' => $activityData]);
            }
            $redis->del($cacheKey);
        } else {
            SimpleLogger::info('redis nx not release', ['type' => $type, 'activity_data' => $activityData]);
        }
        return $result;
    }

    /**
     * @param $type
     * @param $activityData
     * @return bool
     * 每日完成x次行为，对用户上报任务的次数对基础x取模处理
     */
    public static function judgeUserReportData($type, $activityData)
    {
        $field = self::EVERY_DAY_ACTIVITY_REPORT_STATUS;
        $key = self::getActivityTaskFinishRelateKey($activityData['student_id'], NULL, $type);
        $redis = RedisDB::getConn();
        $value = $redis->hget($key, $field);
        $taskInfoArr = self::getActivityTemplate($type);
        $condition = json_decode(reset($taskInfoArr)['condition'], true);
        //基础上报次数
        $baseReportNum = $condition['base_report_num'] ?? 1;
        //已经上报的次数
        return  intval($value) % $baseReportNum == 0;
    }

    /**
     * @param $type
     * @param $activityData
     * 记录当天上报的数据
     */
    private static function recordActivityReport($type, $activityData)
    {
        $field = self::EVERY_DAY_ACTIVITY_REPORT_STATUS;
        $date = date('Y-m-d');
        $key = self::getActivityTaskFinishRelateKey($activityData['student_id'], $date, $type);
        $redis = RedisDB::getConn();
        $value = $redis->hget($key, $field);
        if (empty($value)) {
            $redis->hset($key, $field, 1);
        } else {
            $redis->hset($key, $field, intval($value) + 1);
        }
        //设置过期
        $endTime = strtotime($date) + 172800 - time();
        $redis->expire($key, $endTime);
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

        $activityTemplate = self::getActivityTemplate(self::SIGN_IN_TASKS);
        $shouldGetTaskId = 0;
        //防止需要完成的任务天数大小错乱，引入临时数
        $tmpDay = 0;
        $limitCount = 0;
        $hasAchieveTask = [];
        $award = NULL;
        $awardCondition = [];
        foreach ($activityTemplate as $v) {
            $condition = json_decode($v['condition'], true);
            $continueDays = $condition['continue_days'];
            if ($continueDays >= $tmpDay) {
                $tmpDay = $continueDays;
            }
            if ($data['continue_days'] >= $continueDays && $continueDays >= $tmpDay) {
                $shouldGetTaskId = $v['id'];
                $limitCount = $condition['every_day_count'];
                $award = $v['award'];
                $awardCondition = $condition;
            }
        }
        if (!self::checkTaskQualification($awardCondition, $data)) {
            return;
        }
        $hasAchieveTask[$shouldGetTaskId] = $award;
        //更新用户的完成情况
        self::updateUserCompleteStatus($data['student_id'], self::SIGN_IN_TASKS, $limitCount, $shouldGetTaskId);
        if (self::checkSendAwardTask($data['student_id'], $shouldGetTaskId, $limitCount)) {
            self::dealCreditTaskFinish(self::SIGN_IN_TASKS, $data['uuid'], $shouldGetTaskId, $data);
            return $hasAchieveTask;
        }
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
        $activityTemplate = self::getActivityTemplate(self::PLAY_PIANO_TASKS);
        $hasAchieveTask = [];
        foreach ($activityTemplate as $v) {
            //多个需要不同完成
            if (!empty($completeStatus[$v['id']]['is_complete'])) {
                continue;
            }
            $condition = json_decode($v['condition'], true);
            if ($data['play_duration'] >= $condition['play_duration']) {
                if (!self::checkTaskQualification($condition, $data)) {
                    continue;
                }
                self::updateUserCompleteStatus($data['student_id'], self::PLAY_PIANO_TASKS, $condition['every_day_count'], $v['id']);
                if (self::checkSendAwardTask($data['student_id'], $v['id'], $condition['every_day_count'])) {
                    self::dealCreditTaskFinish(self::PLAY_PIANO_TASKS, $data['uuid'], $v['id'], $data);
                    $hasAchieveTask[$v['id']] = $v['award'];
                }
            }
        }
        return $hasAchieveTask;
    }

    /**
     * @param $data
     * @return array|void
     * 处理每日完成1次双手全曲评测
     */
    public static function bothHandEvaluateAction($data)
    {
        if ($data['score_final'] <= 0) {
            return ;
        }
        return self::oneTypeOneTaskCommonDeal(self::BOTH_HAND_EVALUATE, $data);
    }

    /**
     * @param $data
     * @return array|void
     * 处理每日分享评测成绩
     */
    public static function shareGradeAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::SHARE_GRADE, $data);
    }

    /**
     * @param $data
     * @return array|void
     * 处理音基完成任务
     */
    public static function musicBasicQuestionAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::MUSIC_BASIC_QUESTION, $data);
    }

    /**
     * @param $data
     * @return array|void
     * 处理示范视频完成任务
     */
    public static function exampleVideoAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::EXAMPLE_VIDEO, $data);
    }

    /**
     * 互动课堂，每日首次上课
     * @param $data
     * @return array|void
     */
    public static function attendClassAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::ATTEND_CLASS, $data);
    }

    /**
     * @param $data
     * @return array|void
     * 浏览重难点完成任务
     */
    public static function viewDifficultSpotAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::VIEW_DIFFICULT_SPOT, $data);
    }

    /**
     * @param $data
     * @return array|void
     * 识谱提升完成任务
     */
    public static function knowChartPromotionAction($data)
    {
        return self::oneTypeOneTaskCommonDeal(self::KNOW_CHART_PROMOTION, $data);
    }

    /**
     * @param $type
     * @param $data
     * @return array|void
     * 一种上报类型只对应一个task的通用处理方法
     */
    private static function oneTypeOneTaskCommonDeal($type, $data)
    {
        //对当前任务的完成情况
        $completeStatus = self::getUserCompleteStatus($data['student_id'], $type);
        //只要有一个完成就算完成
        if (!empty($completeStatus)) {
            foreach ($completeStatus as $va) {
                if ($va['is_complete']) {
                    SimpleLogger::info($type . ' has complete', ['activity' => $va]);
                    return;
                }
            }
        }
        $activityTemplate = self::getActivityTemplate($type);
        $hasAchieveTask = [];
        foreach ($activityTemplate as $v) {
            $condition = json_decode($v['condition'], true);
            if (!self::checkTaskQualification($condition, $data)) {
                continue;
            }
            self::updateUserCompleteStatus($data['student_id'], $type, $condition['every_day_count'], $v['id']);
            if (self::checkSendAwardTask($data['student_id'], $v['id'], $condition['every_day_count'])) {
                self::dealCreditTaskFinish($type, $data['uuid'], $v['id'], $data);
                $hasAchieveTask[$v['id']] = $v['award'];
            }
        }
        return $hasAchieveTask;
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
        $field = self::EVERY_DAY_ACTIVITY_COMPLETE_STATUS;
        $key = self::getActivityTaskFinishRelateKey($studentId, $date, $type);
        $data = $redis->hget($key, $field);
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
        $redis->hset($key, $field, json_encode($dataArr));
        //设置过期
        $endTime = strtotime($date) + 172800 - time();
        $redis->expire($key, $endTime);
    }

    /**
     * @param $condition
     * @param $data
     * @return bool
     * 上报数据对应的任务 在业务逻辑的其他限制
     */
    public static function checkTaskQualification($condition, $data)
    {
        if (isset($condition['min_version'])) {
            $res = false;
            if (!empty($data['ignore_version'])) {
                return true;
            }
            //需要检测最小版本
            if (!empty($data['app_version'])) {
                $res = !AIPlayRecordService::isOldVersionApp($data['app_version'], $condition['min_version']);
            }
            if (!$res) {
                SimpleLogger::info('not satisfy min version', ['condition' => $condition, 'data' => $data]);
                return false;
            }
        }

        if (isset($condition['apply_object'])){
            //需要检测适用对象
            $res = true;
            if ($condition['apply_object'] == self::SUB_REMAIN_DURATION_VALID) {
                $res = StudentServiceForApp::getSubStatus($data['student_id']);

            }
            if (!$res) {
                SimpleLogger::info('not apply object', ['condition' => $condition, 'data' => $data]);
                return false;
            }
        }
        return true;
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
        $field = self::EVERY_DAY_ACTIVITY_COMPLETE_STATUS;
        if (!empty($type)) {
            return json_decode($redis->hget(self::getActivityTaskFinishRelateKey($studentId,NULL, $type), $field), true);
        }
        $activityArr = [];
        array_map(function ($item) use(&$activityArr, $redis, $studentId, $field){
            return $activityArr[$item] = json_decode($redis->hget(self::getActivityTaskFinishRelateKey($studentId, NULL, $item), $field), true);
        }, self::getAllReportType());
        return $activityArr;
    }

    /**
     * @param $studentId
     * @param $eventTaskId
     * @param $limitCount
     * @return bool
     * 防止redis丢失再次检测下是否该给奖励
     */
    public static function checkSendAwardTask($studentId, $eventTaskId, $limitCount)
    {
        //当天对当前任务完成了多少次
        $db = MysqlDB::getDB();
        $count = $db->count(PointActivityRecordModel::$table, [
            'student_id' => $studentId,
            'task_id' => $eventTaskId,
            'report_date' => date('Y-m-d')
        ]);
        if ($count >= $limitCount) {
            SimpleLogger::info($eventTaskId . ' has give', ['student_id' => $studentId, 'limit_count' => $limitCount]);
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $type
     * @param $uuid
     * @param $shouldGetTaskId
     * @param $data
     * @param int $status
     * 积分活动任务完成处理
     */
    private static function dealCreditTaskFinish($type, $uuid, $shouldGetTaskId, $data, $status = ErpReferralService::EVENT_TASK_STATUS_COMPLETE)
    {
        $erp = new Erp();
        $erp->updateTask($uuid, $shouldGetTaskId, $status);
        //签到活动完成不产生奖章任务达人增益
        if ($type == self::SIGN_IN_TASKS) {
            return;
        }
        MedalService::recordMedalRelateTaskCount($data);
    }

    /**
     * @param $type
     * @return int
     * 上报类型对应数据库int值
     */
    public static function getReportTypeZhToNum($type)
    {
        $arr = [
            self::SIGN_IN_TASKS => 1,
            self::PLAY_PIANO_TASKS => 2,
            self::BOTH_HAND_EVALUATE => 3,
            self::SHARE_GRADE => 4,
            self::MUSIC_BASIC_QUESTION => 5,
            self::EXAMPLE_VIDEO => 6,
            self::VIEW_DIFFICULT_SPOT => 7,
            self::KNOW_CHART_PROMOTION => 8,
            MedalService::FAMOUS_PERSON => 9
        ];
        return $arr[$type] ?? 0;
    }

    /**
     * 获取任务总数&当前用户完成任务的情况
     * @param $studentId
     * @return array|int
     */
    public static function getActivityList($studentId)
    {
        $activityInfo = EventModel::getRecord(['name' => self::EVERY_DAY_ACTIVITY_NAME, 'status' => EventModel::STATUS_NORMAL]);
        if (empty($activityInfo)) {
            return [0,0];
        }
        $generalTask = EventTaskModel::getCount(['status' => EventTaskModel::STATUS_NORMAL, 'event_id' => $activityInfo['id']]);
        if (empty($studentId)) {
            return [$generalTask, 0];
        }

        $task = EventTaskModel::getRecords(['status' => EventTaskModel::STATUS_NORMAL, 'event_id' => $activityInfo['id']]);
        $taskId = array_column($task, 'id');

        $finishTheTaskDate = PointActivityRecordModel::getStudentFinishTheTask($studentId, $taskId);
        if (empty($finishTheTaskDate)) {
            return [$generalTask, 0];
        }
        $everyDayTaskCount = array_count_values(array_column($finishTheTaskDate, 'task_id'));

        $finishTheTask = Constants::STATUS_FALSE;
        foreach ($task as $item) {
            $conditionInfo = json_decode($item['condition'], true);
            if (!empty($everyDayTaskCount[$item['id']]) && $conditionInfo['every_day_count'] == $everyDayTaskCount[$item['id']]) {
                $finishTheTask += 1;
            }
        }
        return [$generalTask, $finishTheTask];
    }

}